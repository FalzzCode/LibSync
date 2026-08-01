<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Borrowing extends Model
{
    use HasFactory;

    protected $fillable = ['member_id', 'book_id', 'user_id', 'borrowed_at', 'due_date', 'returned_at', 'requested_at', 'approved_at', 'return_requested_at', 'extension_requested_at', 'extension_reason', 'extension_count', 'rejected_at', 'rejected_reason', 'status', 'fine'];

    protected function casts(): array
    {
        return ['borrowed_at' => 'date', 'due_date' => 'date', 'returned_at' => 'date', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'return_requested_at' => 'datetime', 'extension_requested_at' => 'datetime', 'rejected_at' => 'datetime', 'fine' => 'integer', 'extension_count' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fineRecord(): HasOne
    {
        return $this->hasOne(Fine::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['borrowed', 'return_requested']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['borrowed', 'return_requested'])->whereDate('due_date', '<', today());
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['borrowed', 'return_requested']) && $this->due_date->isBefore(today());
    }
}
