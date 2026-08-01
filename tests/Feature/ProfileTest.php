<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_profile_identity(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.test',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nama Baru', 'email' => 'baru@example.test']);
    }

    public function test_student_profile_changes_are_reflected_in_the_linked_member_record(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email' => 'siswa-lama@example.test']);
        $member = Member::create([
            'user_id' => $student->id,
            'name' => 'Nama Lama',
            'email' => 'siswa-lama@example.test',
            'phone' => '08123456789',
        ]);

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'name' => 'Nama Siswa Baru',
                'email' => 'siswa-baru@example.test',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'Nama Siswa Baru', 'email' => 'siswa-baru@example.test']);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'name' => 'Nama Siswa Baru', 'email' => 'siswa-baru@example.test']);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama')]);

        $this->actingAs($user)
            ->patch(route('profile.password'), [
                'current_password' => 'password-lama',
                'password' => 'password-baru-aman',
                'password_confirmation' => 'password-baru-aman',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('password-baru-aman', $user->fresh()->password));
    }

    public function test_user_can_upload_and_replace_their_profile_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/old-photo.png', 'old photo');
        $user = User::factory()->create([
            'email' => 'foto@example.test',
            'profile_photo_path' => 'profile-photos/old-photo.png',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->create('foto-baru.png', 120, 'image/png'),
            ])
            ->assertSessionHas('success');

        $newPhotoPath = $user->fresh()->profile_photo_path;

        $this->assertNotNull($newPhotoPath);
        $this->assertNotSame('profile-photos/old-photo.png', $newPhotoPath);
        Storage::disk('public')->assertMissing('profile-photos/old-photo.png');
        Storage::disk('public')->assertExists($newPhotoPath);
        $this->actingAs($user->fresh())->get(route('profile.photo', $user))->assertOk();
    }

    public function test_profile_email_must_remain_unique(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@example.test']);
        User::factory()->create(['email' => 'sudah-terpakai@example.test']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'sudah-terpakai@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_cannot_change_password_with_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama')]);

        $this->actingAs($user)
            ->patch(route('profile.password'), [
                'current_password' => 'password-salah',
                'password' => 'password-baru-aman',
                'password_confirmation' => 'password-baru-aman',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_user_cannot_access_another_users_profile_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/private.png', 'photo');
        $owner = User::factory()->create(['profile_photo_path' => 'profile-photos/private.png']);
        $otherUser = User::factory()->create(['role' => 'staff']);

        $this->actingAs($otherUser)->get(route('profile.photo', $owner))->assertForbidden();
    }
}
