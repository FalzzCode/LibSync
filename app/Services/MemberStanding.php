<?php

namespace App\Services;

use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Models\Warning;
use Illuminate\Validation\ValidationException;

class MemberStanding
{
    public static function assertCanBorrow(Member $member): void
    {
        $activeLoans = Borrowing::query()->where('member_id', $member->id)->open()->count();
        $maxLoans = (int) SystemSetting::value('max_active_loans', 3);
        $unpaidFine = Fine::query()->where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->exists();

        if ($member->account_status === 'blocked') {
            throw ValidationException::withMessages(['member_id' => 'Akun siswa diblokir: '.($member->block_reason ?: 'hubungi administrator.')]);
        }
        if ($unpaidFine) {
            throw ValidationException::withMessages(['member_id' => 'Siswa masih memiliki denda yang belum dibayar.']);
        }
        if ($activeLoans >= $maxLoans) {
            static::blockAutomatically($member, $activeLoans, $maxLoans);
            throw ValidationException::withMessages(['member_id' => "Akun siswa diblokir otomatis karena telah mencapai batas {$maxLoans} pinjaman aktif."]);
        }
    }

    public static function refresh(Member $member): void
    {
        if ($member->block_type !== 'automatic') {
            return;
        }
        $hasOpenLoans = Borrowing::query()->where('member_id', $member->id)->open()->exists();
        $hasUnpaidFines = Fine::query()->where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->exists();
        if (! $hasOpenLoans && ! $hasUnpaidFines) {
            $before = $member->only(['account_status', 'block_type', 'block_reason', 'blocked_at']);
            $member->update(['account_status' => 'normal', 'block_type' => null, 'block_reason' => null, 'blocked_at' => null]);
            Warning::create(['member_id' => $member->id, 'type' => 'account_unblocked', 'level' => 'info', 'title' => 'Akun aktif kembali', 'message' => 'Seluruh kewajiban siswa telah diselesaikan; blokir otomatis dibuka.']);
            ActivityLogger::write('unblock_automatic', 'member', $member, $before, $member->fresh()->only(['account_status', 'block_type', 'block_reason', 'blocked_at']));
        }
    }

    private static function blockAutomatically(Member $member, int $activeLoans, int $maxLoans): void
    {
        $reason = "Memiliki {$activeLoans} pinjaman aktif; batas maksimal adalah {$maxLoans}.";
        $member->update(['account_status' => 'blocked', 'block_type' => 'automatic', 'block_reason' => $reason, 'blocked_at' => now()]);
        Warning::create(['member_id' => $member->id, 'type' => 'loan_limit', 'level' => 'critical', 'title' => 'Akun diblokir otomatis', 'message' => $reason]);
        ActivityLogger::write('block_automatic', 'member', $member, null, ['reason' => $reason]);
    }
}
