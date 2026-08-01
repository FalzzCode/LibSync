<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentPortalActivation
{
    public static function issue(Member $member): string
    {
        $code = 'LIB-'.Str::upper(Str::random(8));

        $member->update([
            'activation_code_hash' => Hash::make($code),
            'activation_expires_at' => now()->addDays((int) SystemSetting::value('activation_code_days', 14)),
            'activated_at' => null,
        ]);

        return $code;
    }

    public static function findEligibleMember(string $nis, string $code): ?Member
    {
        $member = Member::query()
            ->where('nis', trim($nis))
            ->whereNull('user_id')
            ->first();

        if (! $member || ! $member->activation_code_hash || ! $member->activation_expires_at?->isFuture()) {
            return null;
        }

        return Hash::check(strtoupper(trim($code)), $member->activation_code_hash) ? $member : null;
    }
}
