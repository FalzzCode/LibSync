<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class AuthController extends Controller
{
    private const GOOGLE_LOGIN_ERROR = 'Koneksi ke Google belum dapat diselesaikan. Mulai lagi dari tombol Google di bawah.';

    private const GOOGLE_STATE_COOKIE = 'libsync-google-oauth-state';

    // Menampilkan halaman form login
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    // Memproses percobaan login
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! config('auth.local_login_enabled')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Gunakan tombol Google untuk masuk. Profil siswa baru dibuat otomatis.',
            ]);
        }

        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        // Regenerate session untuk mencegah session fixation
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        if (! $this->googleIsConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Login Google belum dikonfigurasi. Pastikan Client ID, Client Secret, dan URL callback sudah diisi.',
            ]);
        }

        try {
            // The normal Socialite state lives in the Laravel session. Some
            // mobile browsers can replace that session while returning from
            // accounts.google.com. Keep the same CSRF protection in a short
            // lived, encrypted first-party cookie instead.
            $state = Str::random(64);
            $response = $this->googleProvider()
                ->with(['state' => $state])
                ->redirect();

            return $response->withCookie(cookie(
                self::GOOGLE_STATE_COOKIE,
                $state,
                10,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        } catch (\Throwable $exception) {
            $this->logGoogleFailure('redirect', $exception, request());

            return redirect()->route('login')->withErrors([
                'email' => self::GOOGLE_LOGIN_ERROR,
            ]);
        }
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $this->ensureGoogleStateIsValid($request);
            $googleUser = $this->googleProvider()->user();
            $googleEmail = strtolower(trim((string) $googleUser->getEmail()));
            $googleId = trim((string) $googleUser->getId());

            if ($googleEmail === '' || ! filter_var($googleEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('google_email_missing');
            }
            if ($googleId === '') {
                throw new \RuntimeException('google_id_missing');
            }

            $allowedGoogleDomain = trim((string) config('services.google.allowed_domain'));
            if ($allowedGoogleDomain && ! $this->emailBelongsToDomain($googleEmail, $allowedGoogleDomain)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Gunakan akun Google dengan domain sekolah yang terdaftar.',
                ]);
            }

            $activationMemberId = $request->session()->pull('student_activation_member_id');

            if ($activationMemberId) {
                return $this->activateStudentWithGoogle($request, (int) $activationMemberId, $googleUser);
            }

            $user = $this->resolveGoogleUser($googleUser, $googleId, $googleEmail);

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended($user->role === 'student' ? route('student.dashboard') : route('dashboard'));
        } catch (\Throwable $exception) {
            $this->logGoogleFailure('callback', $exception, $request);

            return redirect()->route('login')->withErrors([
                'email' => $this->googleFailureMessage($exception),
            ]);
        } finally {
            Cookie::queue(Cookie::forget(self::GOOGLE_STATE_COOKIE));
        }
    }

    private function activateStudentWithGoogle(Request $request, int $memberId, mixed $googleUser): RedirectResponse
    {
        $googleEmail = strtolower(trim((string) $googleUser->getEmail()));
        if ($googleEmail === '' || ! filter_var($googleEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('student.activation.create')->withErrors(['activation_code' => 'Google tidak mengirim alamat email. Gunakan akun Google lain.']);
        }

        try {
            $user = DB::transaction(function () use ($memberId, $googleUser, $googleEmail) {
                $member = Member::lockForUpdate()->findOrFail($memberId);

                if ($member->user_id || ! $member->activation_code_hash || ! $member->activation_expires_at?->isFuture()) {
                    throw new \RuntimeException('Kode aktivasi sudah tidak berlaku. Minta kode baru kepada petugas.');
                }
                if (User::where('google_id', $googleUser->getId())->orWhere('email', $googleEmail)->exists()) {
                    throw new \RuntimeException('Akun Google ini sudah terhubung ke akun LibSync lain.');
                }
                if (Member::where('email', $googleEmail)->whereKeyNot($member->id)->exists()) {
                    throw new \RuntimeException('Email Google ini sudah dipakai pada data anggota lain.');
                }

                $user = User::create([
                    'name' => $member->name,
                    'email' => $googleEmail,
                    'password' => Str::random(48),
                    'role' => 'student',
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);

                $member->update([
                    'user_id' => $user->id,
                    'email' => $googleEmail,
                    'activated_at' => now(),
                    'activation_code_hash' => null,
                    'activation_expires_at' => null,
                ]);
                ActivityLogger::write('activate_student_portal', 'member', $member, null, ['user_id' => $user->id]);

                return $user;
            });
        } catch (\RuntimeException $exception) {
            return redirect()->route('student.activation.create')->withErrors(['activation_code' => $exception->getMessage()]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard')->with('success', 'Akun siswa berhasil diaktifkan. Selamat datang di LibSync.');
    }

    /**
     * Resolve an existing Google identity or provision a least-privilege
     * student profile for a first-time Google sign-in.
     *
     * Email is the portable identity used by schools that do not issue NIS.
     * Staff/admin/developer roles are never created by this path; only a
     * pre-existing account can retain those privileges.
     */
    private function resolveGoogleUser(mixed $googleUser, string $googleId, string $googleEmail): User
    {
        return DB::transaction(function () use ($googleUser, $googleId, $googleEmail): User {
            $userByGoogleId = User::query()
                ->where('google_id', $googleId)
                ->lockForUpdate()
                ->first();
            $userByEmail = User::query()
                ->whereRaw('LOWER(email) = ?', [$googleEmail])
                ->lockForUpdate()
                ->first();

            if ($userByGoogleId && $userByEmail && $userByGoogleId->id !== $userByEmail->id) {
                throw new \RuntimeException('google_identity_conflict');
            }

            $user = $userByGoogleId ?: $userByEmail;
            $avatarUrl = $this->googleAvatar($googleUser);

            if ($user) {
                if ($user->google_id && $user->google_id !== $googleId) {
                    throw new \RuntimeException('google_identity_conflict');
                }

                $user->forceFill(array_filter([
                    'google_id' => $googleId,
                    'avatar_url' => $avatarUrl,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ], static fn (mixed $value): bool => $value !== null))->save();

                if ($user->role === 'student') {
                    $this->ensureStudentMember($user, $googleEmail, $googleUser);
                }

                return $user->fresh();
            }

            $member = Member::query()
                ->whereRaw('LOWER(email) = ?', [$googleEmail])
                ->lockForUpdate()
                ->first();

            if ($member?->user_id) {
                $linkedUser = User::query()->lockForUpdate()->find($member->user_id);

                if ($linkedUser && $linkedUser->google_id && $linkedUser->google_id !== $googleId) {
                    throw new \RuntimeException('google_identity_conflict');
                }

                if ($linkedUser) {
                    $linkedUser->forceFill(array_filter([
                        'google_id' => $googleId,
                        'avatar_url' => $avatarUrl,
                        'email_verified_at' => $linkedUser->email_verified_at ?? now(),
                    ], static fn (mixed $value): bool => $value !== null))->save();

                    return $linkedUser->fresh();
                }
            }

            if ($member) {
                $user = $this->createGoogleStudent($googleId, $googleEmail, $member->name, $avatarUrl);
                $member->update([
                    'user_id' => $user->id,
                    'email' => $googleEmail,
                    'activated_at' => $member->activated_at ?? now(),
                    'activation_code_hash' => null,
                    'activation_expires_at' => null,
                ]);
                ActivityLogger::write('google_student_linked', 'member', $member, null, ['user_id' => $user->id]);

                return $user;
            }

            if (! config('services.google.auto_register_students', true)) {
                throw new \RuntimeException('google_registration_disabled');
            }

            $name = $this->googleProfileName($googleUser, $googleEmail);
            $user = $this->createGoogleStudent($googleId, $googleEmail, $name, $avatarUrl);
            $member = Member::create([
                'user_id' => $user->id,
                'name' => $name,
                'email' => $googleEmail,
                'phone' => null,
                'activated_at' => now(),
            ]);
            ActivityLogger::write('google_student_registered', 'member', $member, null, ['user_id' => $user->id, 'email' => $googleEmail]);

            return $user;
        });
    }

    private function ensureStudentMember(User $user, string $googleEmail, mixed $googleUser): Member
    {
        $member = Member::query()->where('user_id', $user->id)->lockForUpdate()->first();

        if (! $member) {
            $member = Member::query()
                ->whereRaw('LOWER(email) = ?', [$googleEmail])
                ->lockForUpdate()
                ->first();

            if ($member?->user_id && $member->user_id !== $user->id) {
                throw new \RuntimeException('google_email_conflict');
            }
        }

        if (! $member) {
            $member = Member::create([
                'user_id' => $user->id,
                'name' => $user->name ?: $this->googleProfileName($googleUser, $googleEmail),
                'email' => $googleEmail,
                'phone' => null,
                'activated_at' => now(),
            ]);
        } else {
            $member->update(array_filter([
                'user_id' => $user->id,
                'email' => $member->email ?: $googleEmail,
                'activated_at' => $member->activated_at ?? now(),
            ], static fn (mixed $value): bool => $value !== null));
        }

        return $member;
    }

    private function createGoogleStudent(string $googleId, string $googleEmail, string $name, ?string $avatarUrl): User
    {
        return User::create([
            'name' => $name,
            'email' => $googleEmail,
            'password' => Str::random(64),
            'role' => 'student',
            'google_id' => $googleId,
            'avatar_url' => $avatarUrl,
            'email_verified_at' => now(),
        ]);
    }

    private function googleProfileName(mixed $googleUser, string $email): string
    {
        try {
            $name = trim((string) $googleUser->getName());
        } catch (\Throwable) {
            $name = '';
        }

        if ($name === '') {
            $name = Str::of(Str::before($email, '@'))
                ->replace(['.', '_', '-'], ' ')
                ->title()
                ->toString();
        }

        return Str::limit($name, 255, '');
    }

    private function googleAvatar(mixed $googleUser): ?string
    {
        try {
            $avatar = $googleUser->getAvatar();
        } catch (\Throwable) {
            $avatar = null;
        }

        return is_string($avatar) && trim($avatar) !== '' ? trim($avatar) : null;
    }

    private function emailBelongsToDomain(string $email, string $domain): bool
    {
        $domain = strtolower(ltrim(trim($domain), '@'));

        return str_ends_with($email, '@'.$domain);
    }

    // Memproses logout
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function googleIsConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * @return AbstractProvider
     */
    private function googleProvider()
    {
        return Socialite::driver('google')->stateless();
    }

    private function ensureGoogleStateIsValid(Request $request): void
    {
        $cookieState = (string) $request->cookie(self::GOOGLE_STATE_COOKIE);
        $requestState = (string) $request->input('state');

        if ($cookieState === '' || $requestState === '' || ! hash_equals($cookieState, $requestState)) {
            throw new \RuntimeException('invalid_oauth_state');
        }
    }

    private function googleFailureMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($message === 'invalid_oauth_state') {
            return 'Sesi login Google telah berakhir atau berubah. Tekan tombol Google sekali lagi dari halaman ini.';
        }

        if ($message === 'google_email_missing' || $message === 'google_id_missing') {
            return 'Google tidak mengirim identitas lengkap. Pilih akun Google lain lalu coba lagi.';
        }

        if ($message === 'google_identity_conflict' || $message === 'google_email_conflict') {
            return 'Akun Google ini sudah terhubung ke profil LibSync lain. Hubungi petugas jika perlu menggabungkan data.';
        }

        if ($message === 'google_registration_disabled') {
            return 'Pendaftaran otomatis sedang dimatikan. Minta petugas menghubungkan akun Google Anda.';
        }

        $providerError = $this->googleProviderErrorCode($exception);

        if (in_array($providerError, ['invalid_client', 'unauthorized_client'], true)) {
            return 'Kredensial Google di server tidak cocok. Administrator perlu memperbarui Client Secret di pengaturan hosting.';
        }

        if ($providerError === 'invalid_grant') {
            return 'Kode login Google sudah tidak berlaku. Mulai login kembali dari tombol Google.';
        }

        return self::GOOGLE_LOGIN_ERROR;
    }

    private function googleProviderErrorCode(\Throwable $exception): ?string
    {
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $payload = json_decode((string) $exception->getResponse()->getBody(), true);
            $error = is_array($payload) ? ($payload['error'] ?? null) : null;

            if (is_string($error) && $error !== '') {
                return strtolower($error);
            }
        }

        foreach (['invalid_client', 'unauthorized_client', 'invalid_grant'] as $error) {
            if (str_contains(strtolower($exception->getMessage()), $error)) {
                return $error;
            }
        }

        return null;
    }

    private function logGoogleFailure(string $stage, \Throwable $exception, Request $request): void
    {
        Log::error('Google OAuth flow failed.', [
            'stage' => $stage,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'path' => $request->path(),
        ]);
    }
}
