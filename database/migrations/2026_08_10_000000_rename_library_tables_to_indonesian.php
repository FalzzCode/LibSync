<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only operational library tables are renamed. Laravel's own tables
     * (cache, jobs, sessions, migrations, and password reset tokens) stay
     * unchanged so framework services keep their conventional configuration.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'users' => 'pengguna',
        'categories' => 'kategori',
        'books' => 'buku',
        'members' => 'anggota',
        'borrowings' => 'peminjaman',
        'system_settings' => 'pengaturan_sistem',
        'warnings' => 'peringatan',
        'activity_logs' => 'log_aktivitas',
        'fines' => 'denda',
        'fine_payments' => 'pembayaran_denda',
        'book_copies' => 'salinan_buku',
        'book_reservations' => 'reservasi_buku',
    ];

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (self::TABLES as $from => $to) {
                if (! Schema::hasTable($from)) {
                    continue;
                }

                if (Schema::hasTable($to)) {
                    throw new RuntimeException("Tidak dapat mengganti tabel {$from}: tabel {$to} sudah ada.");
                }

                Schema::rename($from, $to);
            }
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (array_reverse(self::TABLES, true) as $from => $to) {
                if (! Schema::hasTable($to)) {
                    continue;
                }

                if (Schema::hasTable($from)) {
                    throw new RuntimeException("Tidak dapat mengembalikan tabel {$to}: tabel {$from} sudah ada.");
                }

                Schema::rename($to, $from);
            }
        });
    }
};
