<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reports filter by these timestamps on every export. Keep the indexes
     * narrow so payment and circulation writes do not carry unnecessary
     * duplicate index entries.
     */
    public function up(): void
    {
        if (Schema::hasTable('peminjaman') && ! $this->hasIndex('peminjaman', 'peminjaman_borrowed_at_index')) {
            Schema::table('peminjaman', function (Blueprint $table): void {
                $table->index('borrowed_at', 'peminjaman_borrowed_at_index');
            });
        }

        if (Schema::hasTable('pembayaran_denda') && ! $this->hasIndex('pembayaran_denda', 'pembayaran_denda_paid_at_index')) {
            Schema::table('pembayaran_denda', function (Blueprint $table): void {
                $table->index('paid_at', 'pembayaran_denda_paid_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('peminjaman') && $this->hasIndex('peminjaman', 'peminjaman_borrowed_at_index')) {
            Schema::table('peminjaman', function (Blueprint $table): void {
                $table->dropIndex('peminjaman_borrowed_at_index');
            });
        }

        if (Schema::hasTable('pembayaran_denda') && $this->hasIndex('pembayaran_denda', 'pembayaran_denda_paid_at_index')) {
            Schema::table('pembayaran_denda', function (Blueprint $table): void {
                $table->dropIndex('pembayaran_denda_paid_at_index');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
