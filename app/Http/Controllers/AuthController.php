<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private const GOOGLE_LOGIN_ERROR = 'Login Google belum dapat diselesaikan. Muat ulang halaman lalu coba lagi.';

    // Menampilkan halaman form login
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    // Memproses percobaan login
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! app()->environment('local')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Gunakan akun Google yang telah terdaftar untuk masuk.',
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

    public function redirectToGoogle(): RedirectResponse
    {
        if (! $this->googleIsConfigured()) {
            return back()->withErrors([
                'email' => 'Login Google belum dikonfigurasi. Pastikan Client ID, Client Secret, dan URL callback sudah diisi.',
            ]);
        }

        try {
            return Socialite::driver('google')->redirect();
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
            $googleUser = Socialite::driver('google')->user();
            $allowedGoogleDomain = config('services.google.allowed_domain');
            if ($allowedGoogleDomain && ! str_ends_with(strtolower((string) $googleUser->getEmail()), '@'.strtolower($allowedGoogleDomain))) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Gunakan akun Google dengan domain sekolah yang terdaftar.',
                ]);
            }

            $activationMemberId = $request->session()->pull('student_activation_member_id');

            if ($activationMemberId) {
                return $this->activateStudentWithGoogle($request, (int) $activationMemberId, $googleUser);
            }

            $user = User::query()->where('google_id', $googleUser->getId())->orWhere('email', $googleUser->getEmail())->first();

            if (! $user) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Email Google ini belum terdaftar di perpustakaan. Minta petugas untuk membuat atau menghubungkan akun Anda.',
                ]);
            }

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable $exception) {
            $this->logGoogleFailure('callback', $exception, $request);

            return redirect()->route('login')->withErrors([
                'email' => self::GOOGLE_LOGIN_ERROR,
            ]);
        }
    }

    private function activateStudentWithGoogle(Request $request, int $memberId, mixed $googleUser): RedirectResponse
    {
        $googleEmail = $googleUser->getEmail();
        if (! $googleEmail) {
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
