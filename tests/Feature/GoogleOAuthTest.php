<?php

namespace Tests\Feature;

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
    }

    public function test_google_redirect_requires_the_complete_configuration(): void
    {
        config()->set('services.google.redirect', null);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect()
            ->assertSessionHasErrors('email');
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

        $this->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'google_id' => 'google-staff-1',
        ]);

        // Confirm a successful Google callback can reach the destination too;
        // a dashboard error after login must never look like an OAuth failure.
        $this->get(route('dashboard'))->assertOk();
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
            ->assertSessionHasErrors('email', 'Kredensial Google di server tidak cocok. Administrator perlu memperbarui Client Secret di Railway.');
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
