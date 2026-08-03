<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCopy extends Model
{
    protected $table = 'salinan_buku';

    protected $fillable = ['book_id', 'inventory_code', 'barcode', 'condition', 'status', 'acquired_at', 'note'];

    protected function casts(): array
    {
        return ['acquired_at' => 'date'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
