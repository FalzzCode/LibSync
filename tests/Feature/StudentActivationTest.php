<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\StudentPortalActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class StudentActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_creating_member_with_nis_receives_an_activation_code(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->post(route('members.store'), [
            'name' => 'Siswa Aktivasi',
            'nis' => '2026008',
            'phone' => '08123456789',
        ]);

        $response->assertRedirect(route('members.index'))->assertSessionHas('activation_code');
        $member = Member::where('nis', '2026008')->firstOrFail();

        $this->assertNull($member->user_id);
        $this->assertNotNull($member->activation_code_hash);
        $this->assertTrue($member->activation_expires_at->isFuture());
        $this->assertTrue(Hash::check($response->getSession()->get('activation_code'), $member->activation_code_hash));
    }

    public function test_student_can_start_google_activation_with_valid_nis_and_code(): void
    {
        $member = Member::create(['name' => 'Siswa Aktivasi', 'nis' => '2026009', 'phone' => '08123456780']);
        $code = StudentPortalActivation::issue($member);

        $this->post(route('student.activation.store'), [
            'nis' => '2026009',
            'activation_code' => $code,
        ])->assertRedirect(route('auth.google.redirect'))
            ->assertSessionHas('student_activation_member_id', $member->id);
    }

    public function test_google_callback_activates_student_and_consumes_the_code(): void
    {
        $member = Member::create(['name' => 'Siswa Google', 'nis' => '2026010', 'phone' => '08123456781']);
        StudentPortalActivation::issue($member);
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getId')->andReturn('google-student-10');
        $googleUser->shouldReceive('getEmail')->andReturn('siswa.google@example.test');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.test/avatar.png');
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->withSession(['student_activation_member_id' => $member->id])
            ->withCookie('libsync-google-oauth-state', 'valid-google-state')
            ->get(route('auth.google.callback', ['state' => 'valid-google-state']))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pengguna', [
            'name' => 'Siswa Google',
            'email' => 'siswa.google@example.test',
            'role' => 'student',
            'google_id' => 'google-student-10',
        ]);
        $this->assertDatabaseHas('anggota', [
            'id' => $member->id,
            'email' => 'siswa.google@example.test',
            'activation_code_hash' => null,
        ]);
    }
}
