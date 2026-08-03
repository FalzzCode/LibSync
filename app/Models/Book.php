<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $table = 'buku';

    protected $fillable = [
        'title',
        'author',
        'publisher',
        'publication_year',
        'stock',
        'cover_image',
        'category_id',
        'book_code',
        'isbn',
        'shelf',
        'language',
        'page_count',
        'description',
        'archived_at',
    ];

    // Satu buku dimiliki oleh satu kategori
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class);
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        // Prefer the fast static URL when `storage:link` is available. The
        // authenticated controller route keeps covers working on hosts that
        // disallow symlinks (common on shared hosting).
        return is_dir(public_path('storage'))
            ? asset('storage/'.$this->cover_image)
            : route('books.cover', $this);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'page_count' => 'integer'];
    }
}
