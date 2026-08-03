<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StudentReservationController extends Controller
{
    public function store(Book $book): RedirectResponse
    {
        $member = auth()->user()->member;
        abort_unless($member, 403, 'Akun belum terhubung ke data anggota.');
        $createdReservation = null;

        $outcome = DB::transaction(function () use ($book, $member, &$createdReservation) {
            $book = Book::lockForUpdate()->findOrFail($book->id);

            if ($book->archived_at) {
                return 'archived';
            }
            if ($book->stock > 0) {
                return 'available';
            }

            $exists = BookReservation::where('book_id', $book->id)->where('member_id', $member->id)->whereIn('status', ['waiting', 'ready'])->exists();
            if ($exists) {
                return 'exists';
            }
            $position = (int) BookReservation::where('book_id', $book->id)->whereIn('status', ['waiting', 'ready'])->max('queue_position') + 1;
            $createdReservation = BookReservation::create(['book_id' => $book->id, 'member_id' => $member->id, 'status' => 'waiting', 'queue_position' => $position]);

            return 'created';
        });

        if ($outcome === 'available') {
            return back()->with('error', 'Buku masih tersedia. Ajukan peminjaman langsung dari katalog.');
        }
        if ($outcome === 'archived') {
            return back()->with('error', 'Buku ini tidak lagi tersedia.');
        }
        if ($outcome === 'exists') {
            return back()->with('error', 'Buku ini sudah ada dalam daftar tunggu Anda.');
        }

        ActivityLogger::write('create', 'reservation', $createdReservation, null, $createdReservation?->toArray());

        return back()->with('success', 'Buku masuk daftar tunggu. Kami akan memberi tahu saat tersedia.');
    }
}
