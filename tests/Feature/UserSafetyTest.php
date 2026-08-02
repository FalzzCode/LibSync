<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_terakhir_tidak_dapat_diturunkan_perannya(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('users.edit', $admin))->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
        ])->assertRedirect(route('users.edit', $admin));

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_akun_siswa_tidak_muncul_dan_tidak_bisa_diubah_dari_manajemen_pengguna(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee($student->email);
        $this->actingAs($admin)->get(route('users.edit', $student))->assertNotFound();
        $this->actingAs($admin)->put(route('users.update', $student), [
            'name' => $student->name,
            'email' => $student->email,
            'role' => 'staff',
        ])->assertNotFound();

        $this->assertSame('student', $student->fresh()->role);
    }

    public function test_staff_tidak_dapat_mengelola_akun_admin_dan_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $anotherStaff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.edit', $admin))->assertForbidden();
        $this->actingAs($staff)->post(route('users.store'), [
            'name' => 'Admin Baru',
            'email' => 'admin-baru@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ])->assertForbidden();
        $this->actingAs($staff)->put(route('users.update', $anotherStaff), [
            'name' => 'Staff Diubah',
            'email' => $anotherStaff->email,
            'role' => 'admin',
        ])->assertForbidden();
        $this->actingAs($staff)->delete(route('users.destroy', $anotherStaff))->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('staff', $anotherStaff->fresh()->role);
    }
}
