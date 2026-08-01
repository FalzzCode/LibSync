<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('activation_code_hash')->nullable()->after('user_id');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_code_hash');
            $table->timestamp('activated_at')->nullable()->after('activation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['activation_code_hash', 'activation_expires_at', 'activated_at']);
        });
    }
};
