<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
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
        $driver->shouldReceive('user')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
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
