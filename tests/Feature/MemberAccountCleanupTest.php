<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAccountCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_member_without_history_removes_linked_student_account(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $member = Member::create(['user_id' => $student->id, 'name' => 'Siswa Lama', 'phone' => '08123456789']);

        $this->actingAs($staff)->delete(route('members.destroy', $member))->assertRedirect(route('members.index'));

        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }
}
