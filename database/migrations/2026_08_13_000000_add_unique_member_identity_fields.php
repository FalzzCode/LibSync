<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These fields are optional, but when present they identify one member
     * and must not be ambiguous during Google account linking or activation.
     *
     * @var list<string>
     */
    private const COLUMNS = ['email', 'nis', 'nisn'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            $duplicates = DB::table('anggota')
                ->select($column)
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->pluck($column);

            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException(
                    'Tidak dapat membuat identitas anggota unik untuk kolom '.$column.'. Duplikat ditemukan: '.implode(', ', $duplicates->all()).'. Rapikan data terlebih dahulu.'
                );
            }
        }

        Schema::table('anggota', function (Blueprint $table): void {
            $table->unique('email', 'anggota_email_unique');
            $table->unique('nis', 'anggota_nis_unique');
            $table->unique('nisn', 'anggota_nisn_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('anggota')) {
            return;
        }

        Schema::table('anggota', function (Blueprint $table): void {
            $table->dropUnique('anggota_email_unique');
            $table->dropUnique('anggota_nis_unique');
            $table->dropUnique('anggota_nisn_unique');
        });
    }
};
