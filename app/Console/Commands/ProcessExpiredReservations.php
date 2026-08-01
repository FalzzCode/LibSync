<?php

namespace App\Console\Commands;

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
        $expired = BookReservation::where('status', 'ready')->where('expires_at', '<', now())->get();

        foreach ($expired as $reservation) {
            DB::transaction(function () use ($reservation) {
                $reservation = BookReservation::lockForUpdate()->findOrFail($reservation->id);
                if ($reservation->status !== 'ready' || $reservation->expires_at?->isFuture()) {
                    return;
                }
                $reservation->update(['status' => 'expired']);
                $next = BookReservation::with(['member.user', 'book'])->where('book_id', $reservation->book_id)->where('status', 'waiting')->orderBy('queue_position')->lockForUpdate()->first();
                if ($next) {
                    $next->update(['status' => 'ready', 'expires_at' => now()->addDays(3)]);
                    $next->member->user?->notify(new BookReservationReady($next));
                }
            });
        }

        $this->info("{$expired->count()} reservasi kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}
