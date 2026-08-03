<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggota', function (Blueprint $table): void {
            $table->index('email', 'anggota_email_index');
        });

        Schema::table('peminjaman', function (Blueprint $table): void {
            $table->index(['member_id', 'book_id', 'status'], 'peminjaman_member_book_status_index');
        });

        Schema::table('reservasi_buku', function (Blueprint $table): void {
            $table->index(['book_id', 'status', 'queue_position'], 'reservasi_book_queue_index');
            $table->index(['member_id', 'book_id', 'status'], 'reservasi_member_book_status_index');
        });

        Schema::table('peringatan', function (Blueprint $table): void {
            $table->index(['borrowing_id', 'type', 'resolved_at'], 'peringatan_borrowing_type_open_index');
        });
    }

    public function down(): void
    {
        Schema::table('peringatan', function (Blueprint $table): void {
            $table->dropIndex('peringatan_borrowing_type_open_index');
        });

        Schema::table('reservasi_buku', function (Blueprint $table): void {
            $table->dropIndex('reservasi_book_queue_index');
            $table->dropIndex('reservasi_member_book_status_index');
        });

        Schema::table('peminjaman', function (Blueprint $table): void {
            $table->dropIndex('peminjaman_member_book_status_index');
        });

        Schema::table('anggota', function (Blueprint $table): void {
            $table->dropIndex('anggota_email_index');
        });
    }
};
