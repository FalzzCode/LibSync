<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warning extends Model
{
    protected $fillable = ['member_id', 'borrowing_id', 'type', 'level', 'title', 'message', 'read_at', 'resolved_at', 'resolution_note'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }
}
