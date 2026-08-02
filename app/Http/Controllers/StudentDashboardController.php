<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Member;
use App\Models\SystemSetting;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    public function index(): View
    {
        $member = Member::query()->where('user_id', auth()->id())->firstOrFail();
        $borrowings = Borrowing::query()->with('book')->where('member_id', $member->id)->latest()->get();
        // A return request is still an active loan until staff confirms it.
        // Keeping it in the summary prevents students from thinking the book
        // has already left their responsibility.
        $openBorrowings = $borrowings->whereIn('status', ['borrowed', 'return_requested']);
        $pendingBorrowings = $borrowings->where('status', 'requested');
        $unpaidFines = Fine::query()->where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->get();
        $maxActiveLoans = (int) SystemSetting::value('max_active_loans', 3);
        $accountReady = $member->account_status !== 'blocked'
            && $unpaidFines->isEmpty()
            && $openBorrowings->count() < $maxActiveLoans;
        $accountStatusHint = $member->account_status === 'blocked'
            ? 'Akses peminjaman dibatasi'
            : ($unpaidFines->isNotEmpty() ? 'Selesaikan denda terlebih dahulu' : ($openBorrowings->count() >= $maxActiveLoans ? 'Batas pinjaman tercapai' : 'Akun siap digunakan'));
        $notifications = auth()->user()->notifications()->latest()->take(5)->get();

        return view('student.dashboard', compact('member', 'borrowings', 'openBorrowings', 'pendingBorrowings', 'unpaidFines', 'accountReady', 'accountStatusHint', 'notifications'));
    }
}
