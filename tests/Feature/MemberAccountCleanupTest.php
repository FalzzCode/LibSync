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

        $this->assertSoftDeleted('anggota', ['id' => $member->id]);
        $this->assertDatabaseMissing('pengguna', ['id' => $student->id]);
    }

    public function test_staff_can_restore_an_archived_member(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'name' => 'Siswa Arsip',
            'email' => 'siswa-arsip@example.test',
            'phone' => null,
        ]);
        $member->delete();

        $this->actingAs($staff)
            ->patch(route('members.restore', $member))
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('success', 'Anggota Siswa Arsip berhasil dipulihkan.');

        $this->assertDatabaseHas('anggota', [
            'id' => $member->id,
            'email' => 'siswa-arsip@example.test',
            'deleted_at' => null,
        ]);
    }

    public function test_staff_can_view_archived_members(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Member::create([
            'name' => 'Siswa Diarsipkan',
            'email' => 'siswa-diarsipkan@example.test',
            'phone' => null,
        ])->delete();

        $this->actingAs($staff)
            ->get(route('members.archived'))
            ->assertOk()
            ->assertSee('Siswa Diarsipkan')
            ->assertSee('Pulihkan');
    }

    public function test_staff_can_restore_an_archived_member_from_the_add_form(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'name' => 'Nama Lama',
            'email' => 'siswa-arsip-form@example.test',
            'phone' => null,
        ]);
        $member->delete();

        $this->actingAs($staff)
            ->post(route('members.store'), [
                'name' => 'Nama Baru',
                'email' => 'SISWA-ARSIP-FORM@example.test',
                'class' => 'XI',
                'major' => 'PPLG',
                'entry_year' => now()->year,
            ])
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('success', 'Anggota Nama Baru berhasil dipulihkan.');

        $this->assertDatabaseHas('anggota', [
            'id' => $member->id,
            'name' => 'Nama Baru',
            'email' => 'siswa-arsip-form@example.test',
            'class' => 'XI',
            'major' => 'PPLG',
            'deleted_at' => null,
        ]);
    }
}
