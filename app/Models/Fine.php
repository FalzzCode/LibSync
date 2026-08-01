<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fine extends Model
{
    protected $fillable = ['member_id', 'borrowing_id', 'type', 'amount', 'paid_amount', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_amount' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }

    public function getBalanceAttribute(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }
}
