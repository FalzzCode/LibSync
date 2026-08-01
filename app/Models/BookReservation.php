<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReservation extends Model
{
    protected $fillable = ['book_id', 'member_id', 'status', 'queue_position', 'expires_at', 'fulfilled_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'fulfilled_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
