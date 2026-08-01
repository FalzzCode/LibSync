<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Services\ActivityLogger;
use App\Services\MemberStanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class StudentBorrowingController extends Controller
{
    public function store(Book $book): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        MemberStanding::assertCanBorrow($member);
        if ($book->archived_at) {
            throw ValidationException::withMessages(['book' => 'Buku ini sudah diarsipkan dan tidak dapat dipinjam.']);
        }
        if ($book->stock < 1) {
            throw ValidationException::withMessages(['book' => 'Buku ini sedang tidak tersedia.']);
        }
        $hasReadyReservation = BookReservation::where('book_id', $book->id)->where('status', 'ready')->exists();
        $hasOwnReadyReservation = BookReservation::where('book_id', $book->id)->where('status', 'ready')->where('member_id', $member->id)->exists();
        if ($hasReadyReservation && ! $hasOwnReadyReservation) {
            throw ValidationException::withMessages(['book' => 'Buku ini sedang disiapkan untuk anggota dalam daftar tunggu.']);
        }
        if (Borrowing::where('member_id', $member->id)->where('book_id', $book->id)->whereIn('status', ['requested', 'borrowed', 'return_requested'])->exists()) {
            throw ValidationException::withMessages(['book' => 'Permintaan atau pinjaman buku ini masih aktif.']);
        }

        $borrowing = Borrowing::create(['member_id' => $member->id, 'book_id' => $book->id, 'user_id' => auth()->id(), 'borrowed_at' => today(), 'due_date' => today()->addDays((int) SystemSetting::value('default_loan_days', 7)), 'status' => 'requested', 'requested_at' => now()]);
        ActivityLogger::write('request_borrowing', 'borrowing', $borrowing, null, $borrowing->toArray());

        return back()->with('success', 'Permintaan peminjaman dikirim. Tunggu persetujuan petugas.');
    }

    public function requestReturn(Borrowing $borrowing): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        abort_unless($borrowing->member_id === $member->id, 403);
        if ($borrowing->status !== 'borrowed') {
            throw ValidationException::withMessages(['borrowing' => 'Pinjaman ini tidak dapat diajukan untuk dikembalikan.']);
        }
        $borrowing->update(['status' => 'return_requested', 'return_requested_at' => now()]);
        ActivityLogger::write('request_return', 'borrowing', $borrowing, null, ['status' => 'return_requested']);

        return back()->with('success', 'Permintaan pengembalian dikirim ke petugas.');
    }

    public function requestExtension(Borrowing $borrowing): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        abort_unless($borrowing->member_id === $member->id, 403);
        if ($borrowing->status !== 'borrowed' || $borrowing->extension_requested_at || $borrowing->extension_count >= 1) {
            throw ValidationException::withMessages(['borrowing' => 'Perpanjangan tidak dapat diajukan untuk pinjaman ini.']);
        }
        $borrowing->update(['extension_requested_at' => now()]);
        ActivityLogger::write('request_extension', 'borrowing', $borrowing, null, ['extension_requested_at' => $borrowing->extension_requested_at]);

        return back()->with('success', 'Permintaan perpanjangan dikirim ke petugas.');
    }
}
