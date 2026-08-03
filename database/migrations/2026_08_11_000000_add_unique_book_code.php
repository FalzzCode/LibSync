<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('buku')
            ->select('book_code')
            ->whereNotNull('book_code')
            ->groupBy('book_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('book_code');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak dapat mengaktifkan kode buku unik. Duplikat ditemukan: '.implode(', ', $duplicates->all()).'. Rapikan data terlebih dahulu.'
            );
        }

        Schema::table('buku', function (Blueprint $table): void {
            $table->unique('book_code', 'buku_book_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('buku')) {
            return;
        }

        Schema::table('buku', function (Blueprint $table): void {
            $table->dropUnique('buku_book_code_unique');
        });
    }
};
