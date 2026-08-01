<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->unsignedTinyInteger('extension_count')->default(0)->after('fine');
            $table->timestamp('extension_requested_at')->nullable()->after('return_requested_at');
            $table->text('extension_reason')->nullable()->after('extension_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn(['extension_count', 'extension_requested_at', 'extension_reason']);
        });
    }
};
