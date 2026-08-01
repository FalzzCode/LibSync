<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Member;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    public function index(): View
    {
        $member = Member::query()->where('user_id', auth()->id())->firstOrFail();
        $borrowings = Borrowing::query()->with('book')->where('member_id', $member->id)->latest()->get();
        $openBorrowings = $borrowings->where('status', 'borrowed');
        $unpaidFines = Fine::query()->where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->get();
        $notifications = auth()->user()->notifications()->latest()->take(5)->get();

        return view('student.dashboard', compact('member', 'borrowings', 'openBorrowings', 'unpaidFines', 'notifications'));
    }
}
