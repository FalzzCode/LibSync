<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect', 'https://libsync.test/auth/google/callback');
        config()->set('services.google.auto_register_students', true);
    }

    public function test_google_redirect_requires_the_complete_configuration(): void
    {
        config()->set('services.google.redirect', null);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_login_page_explains_that_google_sign_in_uses_email(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('alamat email Google')
            ->assertSee('Lanjutkan dengan Google');
    }

    public function test_google_redirect_uses_a_short_lived_first_party_state_cookie(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('with')->once()->with(Mockery::on(fn (array $parameters) => isset($parameters['state']) && strlen($parameters['state']) === 64))->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://accounts.google.test/oauth')
            ->assertCookie('libsync-google-oauth-state');
    }

    public function test_google_callback_signs_in_a_registered_staff_account(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@example.test',
            'google_id' => null,
        ]);
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->andReturn('google-staff-1');
        $googleUser->shouldReceive('getEmail')->andReturn('staff@example.test');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.test/avatar.png');
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        // A student URL can be left in the guest session before a staff
        // account completes Google sign-in; the role-safe redirect must
        // return the staff member to the staff dashboard instead.
        $this->get(route('student.catalog'))->assertRedirect(route('login'));

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('pengguna', [
            'id' => $staff->id,
            'google_id' => 'google-staff-1',
        ]);

        // Confirm a successful Google callback can reach the destination too;
        // a dashboard error after login must never look like an OAuth failure.
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_google_callback_accepts_long_google_avatar_urls(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff-avatar@example.test',
            'google_id' => null,
        ]);
        $avatarUrl = 'https://lh3.googleusercontent.com/'.str_repeat('a', 700).'-s96-c';
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->andReturn('google-staff-avatar-1');
        $googleUser->shouldReceive('getEmail')->andReturn($staff->email);
        $googleUser->shouldReceive('getAvatar')->andReturn($avatarUrl);
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($avatarUrl, $staff->fresh()->avatar_url);
    }

    public function test_first_time_google_sign_in_creates_a_student_profile_without_nis(): void
    {
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->once()->andReturn('google-new-student-1');
        $googleUser->shouldReceive('getEmail')->once()->andReturn('Siswa.Baru@example.test');
        $googleUser->shouldReceive('getName')->once()->andReturn('Siswa Baru');
        $googleUser->shouldReceive('getAvatar')->once()->andReturn('https://example.test/student.png');
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'siswa.baru@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('student', $user->role);
        $this->assertDatabaseHas('pengguna', [
            'id' => $user->id,
            'google_id' => 'google-new-student-1',
        ]);
        $this->assertDatabaseHas('anggota', [
            'user_id' => $user->id,
            'name' => 'Siswa Baru',
            'email' => 'siswa.baru@example.test',
            'nis' => null,
            'phone' => null,
        ]);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Siswa Baru');
    }

    public function test_google_sign_in_links_an_existing_member_by_email_without_nis(): void
    {
        $member = Member::create([
            'name' => 'Anggota Lama',
            'email' => 'anggota@example.test',
            'phone' => null,
        ]);
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->once()->andReturn('google-member-1');
        $googleUser->shouldReceive('getEmail')->once()->andReturn('ANGGOTA@example.test');
        $googleUser->shouldReceive('getAvatar')->once()->andReturn(null);
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('anggota', [
            'id' => $member->id,
            'user_id' => User::where('email', 'anggota@example.test')->value('id'),
        ]);
        $this->assertNotNull($member->fresh()->activated_at);
        $this->assertDatabaseHas('pengguna', [
            'email' => 'anggota@example.test',
            'role' => 'student',
            'google_id' => 'google-member-1',
        ]);
    }

    public function test_google_sign_in_does_not_crash_when_member_email_belongs_to_archived_data(): void
    {
        $member = Member::create([
            'name' => 'Anggota Arsip',
            'email' => 'arsip@example.test',
            'phone' => null,
        ]);
        $member->delete();

        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->once()->andReturn('google-archived-1');
        $googleUser->shouldReceive('getEmail')->once()->andReturn('arsip@example.test');
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email', 'Data anggota untuk email ini sudah diarsipkan. Hubungi petugas sebelum login kembali.');

        $this->assertDatabaseMissing('pengguna', ['email' => 'arsip@example.test']);
    }

    public function test_unknown_google_account_can_be_rejected_when_auto_registration_is_disabled(): void
    {
        config()->set('services.google.auto_register_students', false);
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->once()->andReturn('google-disabled-1');
        $googleUser->shouldReceive('getEmail')->once()->andReturn('belum-terdaftar@example.test');
        $googleUser->shouldReceive('getAvatar')->once()->andReturn(null);
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email', 'Pendaftaran otomatis sedang dimatikan. Minta petugas menghubungkan akun Google Anda.');

        $this->assertDatabaseMissing('pengguna', ['email' => 'belum-terdaftar@example.test']);
    }

    public function test_google_callback_returns_to_login_when_the_provider_fails(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_google_callback_explains_when_the_client_secret_is_rejected(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andThrow(new ClientException(
            'Google rejected the client',
            new Request('POST', 'https://oauth2.googleapis.com/token'),
            new Response(401, [], '{"error":"invalid_client"}'),
        ));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email', 'Kredensial Google di server tidak cocok. Administrator perlu memperbarui Client Secret di pengaturan hosting.');
    }

    public function test_google_callback_rejects_a_missing_or_mismatched_state_without_contacting_google(): void
    {
        $this->withCookie('libsync-google-oauth-state', 'original-google-state')
            ->get(route('auth.google.callback', ['state' => 'different-google-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_unhandled_oauth_errors_render_a_safe_page_instead_of_a_generic_500(): void
    {
        Route::get('/auth/google/testing-failure', function () {
            throw new \RuntimeException('Simulated middleware failure');
        });

        $this->get('/auth/google/testing-failure')
            ->assertStatus(503)
            ->assertSee('Login Google belum dapat diteruskan.');
    }
}
