<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookReservation;
use App\Notifications\BookReservationReady;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessExpiredReservations extends Command
{
    protected $signature = 'library:process-reservations';

    protected $description = 'Mengakhiri reservasi yang melewati batas ambil dan menawarkan ke antrean berikutnya.';

    public function handle(): int
    {
        $count = 0;
        BookReservation::query()
            ->where('status', 'ready')
            ->where('expires_at', '<', now())
            ->select('id')
            ->chunkById(200, function ($reservations) use (&$count): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$count): void {
                        // Lock the book first, matching circulation and new
                        // reservations, then serialize the queue transition.
                        $lockedReservation = BookReservation::query()->find($reservation->id);
                        if (! $lockedReservation) {
                            return;
                        }
                        $book = Book::lockForUpdate()->find($lockedReservation->book_id);
                        if (! $book) {
                            return;
                        }
                        $lockedReservation = BookReservation::with(['member.user', 'book'])
                            ->lockForUpdate()
                            ->find($lockedReservation->id);
                        if (! $lockedReservation) {
                            return;
                        }
                        if ($lockedReservation->status !== 'ready' || $lockedReservation->expires_at?->isFuture()) {
                            return;
                        }

                        $lockedReservation->update(['status' => 'expired']);
                        $next = BookReservation::with(['member.user', 'book'])
                            ->where('book_id', $book->id)
                            ->where('status', 'waiting')
                            ->orderBy('queue_position')
                            ->lockForUpdate()
                            ->first();
                        if ($next) {
                            $next->update(['status' => 'ready', 'expires_at' => now()->addDays(3)]);
                            $next->member->user?->notify(new BookReservationReady($next));
                        }
                        $count++;
                    });
                }
            });

        $this->info("{$count} reservasi kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}
