<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('members') || ! Schema::hasColumn('members', 'phone')) {
            return;
        }

        // A rollback needs a valid value for the original NOT NULL column.
        // Keep the data recoverable by using an empty value rather than a
        // fabricated phone number.
        DB::table('members')->whereNull('phone')->update(['phone' => '']);

        Schema::table('members', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
