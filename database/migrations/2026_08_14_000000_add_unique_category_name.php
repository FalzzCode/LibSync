<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('kategori')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak dapat membuat nama kategori unik. Duplikat ditemukan: '.implode(', ', $duplicates->all()).'. Rapikan data terlebih dahulu.'
            );
        }

        Schema::table('kategori', function (Blueprint $table): void {
            $table->unique('name', 'kategori_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kategori')) {
            return;
        }

        Schema::table('kategori', function (Blueprint $table): void {
            $table->dropUnique('kategori_name_unique');
        });
    }
};
