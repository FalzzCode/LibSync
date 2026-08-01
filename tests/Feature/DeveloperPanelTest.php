<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_prepare_and_switch_between_test_roles_without_reauthenticating(): void
    {
        $developer = User::factory()->create(['role' => 'developer']);

        $this->actingAs($developer)
            ->post(route('developer.prepare-role-tests'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $student = User::where('email', 'developer.student@libsync.test')->firstOrFail();
        $this->assertDatabaseHas('users', ['email' => 'developer.admin@libsync.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'developer.staff@libsync.test', 'role' => 'staff']);
        $this->assertDatabaseHas('members', ['user_id' => $student->id, 'nis' => 'DEV-STUDENT-001']);

        $this->post(route('developer.switch-role'), ['role' => 'student'])
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');
        $this->assertAuthenticatedAs($student);

        $this->get(route('developer.index'))->assertOk();

        $this->post(route('developer.restore-user'))
            ->assertRedirect(route('developer.index'))
            ->assertSessionHas('success');
        $this->assertAuthenticatedAs($developer);
    }

    public function test_admin_cannot_open_developer_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('developer.index'))->assertNotFound();
    }
}
