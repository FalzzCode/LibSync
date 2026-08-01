<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'class',
        'address',
        'phone',
        'nis',
        'nisn',
        'major',
        'gender',
        'email',
        'entry_year',
        'account_status',
        'block_type',
        'block_reason',
        'blocked_at',
        'user_id',
        'activation_code_hash',
        'activation_expires_at',
        'activated_at',
    ];

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class);
    }

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'activation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'entry_year' => 'integer',
        ];
    }
}
