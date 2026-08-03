<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.local_login_enabled', true);
    }

    public function test_local_login_is_hidden_until_fallback_is_requested(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Login alternatif');

        $this->get(route('login', ['local' => 1]))
            ->assertOk()
            ->assertSee('Login alternatif')
            ->assertSee('Masuk dengan password');
    }

    public function test_local_user_can_sign_in_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@perpustakaan.test',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionDoesntHaveErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_local_login_normalizes_email_case_and_outer_spaces(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@perpustakaan.test',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        $this->post(route('login.attempt'), [
            'email' => '  STAFF@PERPUSTAKAAN.TEST  ',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_local_login_ignores_a_stale_intended_url_from_another_role(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@perpustakaan.test',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        $this->get(route('student.catalog'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_local_credentials_return_a_clear_error(): void
    {
        User::factory()->create([
            'email' => 'staff@perpustakaan.test',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => 'staff@perpustakaan.test',
                'password' => 'password-salah',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_login_can_be_disabled_without_removing_the_form_request(): void
    {
        config()->set('auth.local_login_enabled', false);

        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => 'staff@perpustakaan.test',
                'password' => 'password123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_production_member_form_cannot_create_local_student_accounts(): void
    {
        config()->set('auth.local_login_enabled', false);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->from(route('members.create'))->post(route('members.store'), [
            'name' => 'Siswa Produksi',
            'account_email' => 'siswa@example.test',
            'account_password' => 'password123',
        ])->assertRedirect(route('members.create'))->assertSessionHasErrors('account_email');

        $this->assertDatabaseMissing('pengguna', ['email' => 'siswa@example.test']);
        $this->assertDatabaseMissing('anggota', ['name' => 'Siswa Produksi']);
    }
}
