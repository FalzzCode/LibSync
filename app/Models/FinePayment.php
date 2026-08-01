<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinePayment extends Model
{
    protected $fillable = ['fine_id', 'amount', 'method', 'note', 'received_by', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'integer'];
    }

    public function fine(): BelongsTo
    {
        return $this->belongsTo(Fine::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
