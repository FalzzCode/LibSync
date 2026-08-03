<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnBorrowingRequest;
use App\Http\Requests\StoreBorrowingRequest;
use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Models\Warning;
use App\Notifications\BookReservationReady;
use App\Services\ActivityLogger;
use App\Services\MemberStanding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['borrowed', 'overdue', 'returned', 'requested', 'return_requested', 'extension_requested'])],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;
        if ($from && $until && strtotime($from) > strtotime($until)) {
            [$from, $until] = [$until, $from];
        }

        $borrowings = Borrowing::with(['member', 'book', 'user'])
            ->when($search !== '', function (Builder $query) use ($search) {
                if ($search === '') {
                    return;
                }
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('member', fn (Builder $member) => $member->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('book', fn (Builder $book) => $book->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'borrowed', fn (Builder $query) => $query->where('status', 'borrowed')->whereDate('due_date', '>=', today()))
            ->when($status === 'overdue', fn (Builder $query) => $query->overdue())
            ->when($status === 'returned', fn (Builder $query) => $query->where('status', 'returned'))
            ->when($status === 'requested', fn (Builder $query) => $query->where('status', 'requested'))
            ->when($status === 'return_requested', fn (Builder $query) => $query->where('status', 'return_requested'))
            ->when($status === 'extension_requested', fn (Builder $query) => $query->whereIn('status', ['borrowed', 'return_requested'])->whereNotNull('extension_requested_at'))
            ->when($from, fn (Builder $query) => $query->whereDate('borrowed_at', '>=', $from))
            ->when($until, fn (Builder $query) => $query->whereDate('borrowed_at', '<=', $until))
            ->latest('borrowed_at')->latest('id')->get();

        return view('borrowings.index', compact('borrowings'));
    }

    public function create(): View
    {
        return view('borrowings.create', [
            'members' => Member::orderBy('name')->get(),
            'books' => Book::with('category')->where('stock', '>', 0)->orderBy('title')->get(),
        ]);
    }

    public function store(StoreBorrowingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $standingFailure = null;

        // Lock the member before checking the standing. This keeps concurrent
        // staff/student requests from both passing the active-loan limit.
        // A standing failure is caught so an automatic block/warning commits.
        DB::transaction(function () use ($data, &$standingFailure) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            try {
                MemberStanding::assertCanBorrow($member);
            } catch (ValidationException $exception) {
                $standingFailure = $exception;

                return;
            }

            $book = Book::lockForUpdate()->findOrFail($data['book_id']);
            if ($book->archived_at) {
                throw ValidationException::withMessages(['book_id' => 'Buku ini sudah diarsipkan dan tidak dapat dipinjam.']);
            }
            if ($book->stock < 1) {
                throw ValidationException::withMessages(['book_id' => 'Buku sedang tidak tersedia. Pilih buku lain.']);
            }
            $readyReservations = BookReservation::where('book_id', $book->id)
                ->where('status', 'ready')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->get();
            $readyReservation = $readyReservations->firstWhere('member_id', (int) $data['member_id']);
            if ($readyReservations->isNotEmpty() && ! $readyReservation) {
                throw ValidationException::withMessages(['book_id' => 'Buku ini sedang disiapkan untuk anggota dalam daftar tunggu.']);
            }

            $alreadyBorrowed = Borrowing::where('member_id', $data['member_id'])
                ->where('book_id', $book->id)->where('status', 'borrowed')->exists();
            if ($alreadyBorrowed) {
                throw ValidationException::withMessages(['book_id' => 'Anggota ini masih meminjam buku yang sama.']);
            }

            $borrowing = Borrowing::create($data + ['user_id' => auth()->id(), 'status' => 'borrowed', 'fine' => 0]);
            $book->decrement('stock');
            $readyReservation?->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
            ActivityLogger::write('create', 'borrowing', $borrowing, null, $borrowing->toArray());
        });

        if ($standingFailure) {
            throw $standingFailure;
        }

        return redirect()->route('borrowings.index')->with('success', 'Peminjaman berhasil dicatat dan stok buku diperbarui.');
    }

    public function show(Borrowing $borrowing): View
    {
        $borrowing->load(['member', 'book.category', 'user']);

        return view('borrowings.show', compact('borrowing'));
    }

    public function approve(Borrowing $borrowing): RedirectResponse
    {
        if ($borrowing->status !== 'requested') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }
        $data = ['member_id' => $borrowing->member_id, 'book_id' => $borrowing->book_id];
        $standingFailure = null;
        $approvalError = null;

        DB::transaction(function () use ($borrowing, $data, &$standingFailure, &$approvalError) {
            // Keep the lock order member -> borrowing -> book, matching new
            // loans and returns, so concurrent circulation actions do not
            // deadlock while updating the same member and title.
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            if ($borrowing->status !== 'requested') {
                $approvalError = 'Permintaan ini sudah diproses.';

                return;
            }
            try {
                MemberStanding::assertCanBorrow($member);
            } catch (ValidationException $exception) {
                // The standing service records an automatic block and warning.
                // Catch here so those records can commit before the validation
                // error is shown to the staff member.
                $standingFailure = $exception;

                return;
            }
            $book = Book::lockForUpdate()->findOrFail($data['book_id']);
            if ($book->archived_at) {
                throw ValidationException::withMessages(['borrowing' => 'Buku ini sudah diarsipkan dan tidak dapat dipinjam.']);
            }
            if ($book->stock < 1) {
                throw ValidationException::withMessages(['borrowing' => 'Buku tidak lagi tersedia.']);
            }
            $readyReservations = BookReservation::where('book_id', $book->id)
                ->where('status', 'ready')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->get();
            $readyReservation = $readyReservations->firstWhere('member_id', $member->id);
            if ($readyReservations->isNotEmpty() && ! $readyReservation) {
                throw ValidationException::withMessages(['borrowing' => 'Buku ini sedang disiapkan untuk anggota dalam daftar tunggu.']);
            }
            if (Borrowing::query()
                ->where('member_id', $member->id)
                ->where('book_id', $book->id)
                ->whereIn('status', ['requested', 'borrowed', 'return_requested'])
                ->whereKeyNot($borrowing->id)
                ->exists()) {
                $borrowing->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_reason' => 'Anggota masih memiliki permintaan atau pinjaman buku yang sama.',
                ]);
                $approvalError = 'Permintaan ditolak karena anggota masih memiliki permintaan atau pinjaman buku yang sama.';

                return;
            }
            $book->decrement('stock');
            $borrowing->update(['status' => 'borrowed', 'user_id' => auth()->id(), 'borrowed_at' => today(), 'due_date' => today()->addDays((int) SystemSetting::value('default_loan_days', 7)), 'approved_at' => now()]);
            $readyReservation?->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
            ActivityLogger::write('approve_borrowing', 'borrowing', $borrowing, null, $borrowing->fresh()->toArray());
        });

        if ($standingFailure) {
            throw $standingFailure;
        }
        if ($approvalError) {
            return back()->with('error', $approvalError);
        }

        return back()->with('success', 'Peminjaman disetujui dan stok diperbarui.');
    }

    public function returnBook(ReturnBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $returnedAt = $request->date('returned_at');
        $memberId = $borrowing->member_id;

        DB::transaction(function () use ($borrowing, $returnedAt, $memberId) {
            // Keep the same member -> borrowing -> book order used by
            // borrowing requests and approvals.
            $member = Member::lockForUpdate()->findOrFail($memberId);
            $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            if (! in_array($borrowing->status, ['borrowed', 'return_requested'])) {
                throw ValidationException::withMessages(['returned_at' => 'Buku ini sudah dikembalikan sebelumnya.']);
            }
            if ($returnedAt->isBefore($borrowing->borrowed_at)) {
                throw ValidationException::withMessages(['returned_at' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.']);
            }

            $lateDays = $returnedAt->isAfter($borrowing->due_date)
                ? $borrowing->due_date->diffInDays($returnedAt)
                : 0;
            $fine = $lateDays * (int) SystemSetting::value('fine_per_day', config('library.fine_per_day'));
            $before = $borrowing->only(['returned_at', 'status', 'fine']);
            $borrowing->update(['returned_at' => $returnedAt, 'status' => 'returned', 'fine' => $fine]);
            Book::lockForUpdate()->findOrFail($borrowing->book_id)->increment('stock');
            $overdueWarning = Warning::query()
                ->where('borrowing_id', $borrowing->id)
                ->where('type', 'overdue')
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->first();
            if ($overdueWarning) {
                $overdueWarning->update([
                    'resolved_at' => now(),
                    'resolution_note' => 'Peminjaman sudah dikembalikan.',
                ]);
                ActivityLogger::write('resolve', 'warning', $overdueWarning, null, [
                    'resolved_at' => $overdueWarning->resolved_at,
                    'resolution_note' => $overdueWarning->resolution_note,
                ]);
            }
            $reservation = BookReservation::with(['member.user', 'book'])
                ->where('book_id', $borrowing->book_id)
                ->where('status', 'waiting')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->first();
            if ($reservation) {
                $reservation->update(['status' => 'ready', 'expires_at' => now()->addDays(3)]);
                if ($reservation->member->user) {
                    $reservation->member->user->notify(new BookReservationReady($reservation));
                }
            }
            if ($fine > 0) {
                Fine::create(['member_id' => $borrowing->member_id, 'borrowing_id' => $borrowing->id, 'type' => 'late', 'amount' => $fine, 'created_by' => auth()->id(), 'note' => "Keterlambatan {$lateDays} hari."]);
                Warning::create(['member_id' => $borrowing->member_id, 'borrowing_id' => $borrowing->id, 'type' => 'late_return', 'level' => 'warning', 'title' => 'Pengembalian terlambat', 'message' => 'Denda keterlambatan sebesar Rp'.number_format($fine, 0, ',', '.').'.']);
            }
            MemberStanding::refresh($member);
            ActivityLogger::write('return', 'borrowing', $borrowing, $before, $borrowing->fresh()->only(['returned_at', 'status', 'fine']));
        });

        return redirect()->route('borrowings.show', $borrowing)->with('success', 'Pengembalian berhasil diproses.');
    }

    public function approveExtension(Borrowing $borrowing): RedirectResponse
    {
        if ($borrowing->status !== 'borrowed' || ! $borrowing->extension_requested_at || $borrowing->extension_count >= 1) {
            return back()->with('error', 'Permintaan perpanjangan tidak valid.');
        }
        $approvalError = null;

        DB::transaction(function () use ($borrowing, &$approvalError) {
            $borrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);
            if ($borrowing->status !== 'borrowed' || ! $borrowing->extension_requested_at || $borrowing->extension_count >= 1) {
                $approvalError = 'Permintaan perpanjangan tidak valid atau sudah diproses.';

                return;
            }
            $before = $borrowing->only(['due_date', 'extension_count', 'extension_requested_at']);
            $borrowing->update(['due_date' => $borrowing->due_date->addDays((int) SystemSetting::value('default_loan_days', 7)), 'extension_count' => $borrowing->extension_count + 1, 'extension_requested_at' => null]);
            ActivityLogger::write('approve_extension', 'borrowing', $borrowing, $before, $borrowing->fresh()->only(['due_date', 'extension_count']));
        });

        if ($approvalError) {
            return back()->with('error', $approvalError);
        }

        return back()->with('success', 'Perpanjangan disetujui.');
    }
}
