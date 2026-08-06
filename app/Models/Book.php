<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    /**
     * Cover bawaan untuk buku contoh Indonesia yang diimpor dari template.
     * Cover upload manual tetap diprioritaskan di dalam coverUrl().
     */
    private const DEFAULT_COVER_URLS = [
        'IND-001' => 'https://cdn.gramedia.com/uploads/product-metas/c7zl00re93.jpg',
        'IND-002' => 'https://cdn.gramedia.com/uploads/picture_meta/2023/12/20/xoid3bznddxudnurccgqxi.jpg',
        'IND-003' => 'https://cdn.gramedia.com/uploads/items/9789793062792_New-Edition-Laskar-Pelangi.jpg',
        'IND-004' => 'https://cdn.gramedia.com/uploads/items/Sang_Pemimpi.jpg',
        'IND-005' => 'https://cdn.gramedia.com/uploads/products/hzc2r732ce.png',
        'IND-006' => 'https://cdn.gramedia.com/uploads/items/img20220905_11493451.jpg',
        'IND-007' => 'https://cdn.gramedia.com/uploads/product-metas/20r-6r2a52.jpg',
        'IND-008' => 'https://cdn.gramedia.com/uploads/items/Rantau_1_Muara.jpg',
        'IND-009' => 'https://cdn.gramedia.com/uploads/items/bumi-manusia-edit.jpg',
        'IND-010' => 'https://cdn.gramedia.com/uploads/product-metas/uh0d0g8ukg.jpg',
        'IND-011' => 'https://cdn.gramedia.com/uploads/items/Cantik_Itu_Luka_20_HC.jpg',
        'IND-012' => 'https://cdn.gramedia.com/uploads/items/ayat-ayat-cinta-2-edit.jpg',
        'IND-013' => 'https://cdn.gramedia.com/uploads/items/filosofi_teras_hc.png',
        'IND-014' => 'https://cdn.gramedia.com/uploads/items/9786020320595_Orang-Orang-Proyek---Cover-Baru.jpg',
        'IND-015' => 'https://cdn.gramedia.com/uploads/items/9786024248215_Nanti-Kita-Cerita-Tentang-Hari-Ini.jpg',
    ];

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
            $bookCode = strtoupper(trim((string) $this->book_code));

            return self::DEFAULT_COVER_URLS[$bookCode] ?? null;
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
