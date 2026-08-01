<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->timestamp('requested_at')->nullable()->after('returned_at');
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            $table->timestamp('return_requested_at')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('return_requested_at');
            $table->text('rejected_reason')->nullable()->after('rejected_at');
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropIndex(['status', 'requested_at']);
            $table->dropColumn(['requested_at', 'approved_at', 'return_requested_at', 'rejected_at', 'rejected_reason']);
        });
    }
};
