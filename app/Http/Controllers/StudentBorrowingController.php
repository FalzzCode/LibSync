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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentBorrowingController extends Controller
{
    public function store(Book $book): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        $memberId = $member->id;
        $standingFailure = null;
        $borrowing = null;

        // Lock the member and book while checking the request. This keeps two
        // fast taps (or a student/admin action at the same time) from creating
        // duplicate requests or approving a book that has just become held.
        $borrowing = DB::transaction(function () use ($book, $memberId, &$standingFailure): ?Borrowing {
            $member = Member::lockForUpdate()->findOrFail($memberId);
            try {
                MemberStanding::assertCanBorrow($member);
            } catch (ValidationException $exception) {
                // Keep the automatic block and warning while returning the
                // validation message to the student after the transaction.
                $standingFailure = $exception;

                return null;
            }

            $book = Book::lockForUpdate()->findOrFail($book->id);

            if ($book->archived_at) {
                throw ValidationException::withMessages(['book' => 'Buku ini sudah diarsipkan dan tidak dapat dipinjam.']);
            }
            if ($book->stock < 1) {
                throw ValidationException::withMessages(['book' => 'Buku ini sedang tidak tersedia.']);
            }

            $readyReservations = BookReservation::where('book_id', $book->id)
                ->where('status', 'ready')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->get();
            if ($readyReservations->isNotEmpty() && ! $readyReservations->firstWhere('member_id', $member->id)) {
                throw ValidationException::withMessages(['book' => 'Buku ini sedang disiapkan untuk anggota dalam daftar tunggu.']);
            }
            if (Borrowing::where('member_id', $member->id)->where('book_id', $book->id)->whereIn('status', ['requested', 'borrowed', 'return_requested'])->exists()) {
                throw ValidationException::withMessages(['book' => 'Permintaan atau pinjaman buku ini masih aktif.']);
            }

            return Borrowing::create([
                'member_id' => $member->id,
                'book_id' => $book->id,
                'user_id' => auth()->id(),
                'borrowed_at' => today(),
                'due_date' => today()->addDays((int) SystemSetting::value('default_loan_days', 7)),
                'status' => 'requested',
                'requested_at' => now(),
            ]);
        });
        if ($standingFailure) {
            throw $standingFailure;
        }
        if (! $borrowing) {
            throw ValidationException::withMessages(['book' => 'Permintaan peminjaman tidak dapat diproses.']);
        }
        ActivityLogger::write('request_borrowing', 'borrowing', $borrowing, null, $borrowing->toArray());

        return back()->with('success', 'Permintaan peminjaman dikirim. Tunggu persetujuan petugas.');
    }

    public function requestReturn(Borrowing $borrowing): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        abort_unless($borrowing->member_id === $member->id, 403);
        $borrowing = DB::transaction(function () use ($borrowing): Borrowing {
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            if ($lockedBorrowing->status !== 'borrowed') {
                throw ValidationException::withMessages(['borrowing' => 'Pinjaman ini tidak dapat diajukan untuk dikembalikan.']);
            }
            $lockedBorrowing->update(['status' => 'return_requested', 'return_requested_at' => now()]);

            return $lockedBorrowing;
        });
        ActivityLogger::write('request_return', 'borrowing', $borrowing, null, ['status' => 'return_requested']);

        return back()->with('success', 'Permintaan pengembalian dikirim ke petugas.');
    }

    public function requestExtension(Borrowing $borrowing): RedirectResponse
    {
        $member = Member::where('user_id', auth()->id())->firstOrFail();
        abort_unless($borrowing->member_id === $member->id, 403);
        $borrowing = DB::transaction(function () use ($borrowing): Borrowing {
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            if ($lockedBorrowing->status !== 'borrowed' || $lockedBorrowing->extension_requested_at || $lockedBorrowing->extension_count >= 1) {
                throw ValidationException::withMessages(['borrowing' => 'Perpanjangan tidak dapat diajukan untuk pinjaman ini.']);
            }
            $lockedBorrowing->update(['extension_requested_at' => now()]);

            return $lockedBorrowing;
        });
        ActivityLogger::write('request_extension', 'borrowing', $borrowing, null, ['extension_requested_at' => $borrowing->extension_requested_at]);

        return back()->with('success', 'Permintaan perpanjangan dikirim ke petugas.');
    }
}
