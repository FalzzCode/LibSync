<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        $settings = [
            'max_active_loans' => (int) SystemSetting::value('max_active_loans', 3),
            'default_loan_days' => (int) SystemSetting::value('default_loan_days', 7),
            'fine_per_day' => (int) SystemSetting::value('fine_per_day', 1000),
            'activation_code_days' => (int) SystemSetting::value('activation_code_days', 14),
        ];

        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_active_loans' => ['required', 'integer', 'min:1', 'max:10'],
            'default_loan_days' => ['required', 'integer', 'min:1', 'max:60'],
            'fine_per_day' => ['required', 'integer', 'min:0', 'max:1000000'],
            'activation_code_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        DB::transaction(function () use ($data): void {
            $before = collect($data)->mapWithKeys(fn ($value, $key) => [$key => SystemSetting::value($key)])->all();
            foreach ($data as $key => $value) {
                SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
            ActivityLogger::write('update', 'settings', null, $before, $data);
        });

        return back()->with('success', 'Pengaturan perpustakaan berhasil diperbarui.');
    }
}
