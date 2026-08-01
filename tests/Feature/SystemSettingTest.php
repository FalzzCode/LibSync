<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_library_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('settings.update'), [
                'max_active_loans' => 4,
                'default_loan_days' => 14,
                'fine_per_day' => 2000,
                'activation_code_days' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('4', SystemSetting::value('max_active_loans'));
        $this->assertSame('14', SystemSetting::value('default_loan_days'));
        $this->assertSame('2000', SystemSetting::value('fine_per_day'));
        $this->assertSame('10', SystemSetting::value('activation_code_days'));
    }

    public function test_staff_cannot_change_library_rules(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->patch(route('settings.update'), [
                'max_active_loans' => 4,
                'default_loan_days' => 14,
                'fine_per_day' => 2000,
                'activation_code_days' => 10,
            ])
            ->assertForbidden();
    }
}
