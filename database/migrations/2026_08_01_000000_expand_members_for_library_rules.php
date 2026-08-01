<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('nis')->nullable()->after('name');
            $table->string('nisn')->nullable()->after('nis');
            $table->string('major')->nullable()->after('class');
            $table->string('gender', 20)->nullable()->after('major');
            $table->string('email')->nullable()->after('phone');
            $table->unsignedSmallInteger('entry_year')->nullable()->after('email');
            $table->string('account_status')->default('normal')->after('entry_year');
            $table->string('block_type')->nullable()->after('account_status');
            $table->text('block_reason')->nullable()->after('block_type');
            $table->timestamp('blocked_at')->nullable()->after('block_reason');
            $table->softDeletes();
            $table->index(['account_status', 'blocked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['account_status', 'blocked_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['nis', 'nisn', 'major', 'gender', 'email', 'entry_year', 'account_status', 'block_type', 'block_reason', 'blocked_at']);
        });
    }
};
