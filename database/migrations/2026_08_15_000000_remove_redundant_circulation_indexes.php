<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These indexes are strict prefixes of the unique/queue indexes added by
     * the circulation hardening migrations. Removing them reduces write cost
     * without changing query semantics or any stored data.
     *
     * @var array<string, string>
     */
    private const REDUNDANT_INDEXES = [
        'anggota' => 'anggota_email_index',
        'buku' => 'books_book_code_index',
        'reservasi_buku' => 'book_reservations_book_id_status_index',
    ];

    public function up(): void
    {
        foreach (self::REDUNDANT_INDEXES as $table => $index) {
            $this->dropIndexIfPresent($table, $index);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('anggota') && ! $this->hasIndex('anggota', 'anggota_email_index')) {
            Schema::table('anggota', function (Blueprint $table): void {
                $table->index('email', 'anggota_email_index');
            });
        }

        if (Schema::hasTable('buku') && ! $this->hasIndex('buku', 'books_book_code_index')) {
            Schema::table('buku', function (Blueprint $table): void {
                $table->index('book_code', 'books_book_code_index');
            });
        }

        if (Schema::hasTable('reservasi_buku') && ! $this->hasIndex('reservasi_buku', 'book_reservations_book_id_status_index')) {
            Schema::table('reservasi_buku', function (Blueprint $table): void {
                $table->index(['book_id', 'status'], 'book_reservations_book_id_status_index');
            });
        }
    }

    private function dropIndexIfPresent(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropIndex($index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
