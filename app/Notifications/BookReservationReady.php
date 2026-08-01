<?php

namespace App\Notifications;

use App\Models\BookReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookReservationReady extends Notification
{
    use Queueable;

    public function __construct(private readonly BookReservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Buku sudah tersedia',
            'message' => "{$this->reservation->book->title} tersedia untuk Anda. Silakan ambil sebelum {$this->reservation->expires_at->translatedFormat('d M Y')}.",
            'book_id' => $this->reservation->book_id,
            'reservation_id' => $this->reservation->id,
            'url' => route('student.catalog'),
        ];
    }
}
