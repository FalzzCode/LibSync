<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrowing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('late');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->string('status')->default('unpaid');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'status']);
        });
        Schema::create('fine_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method')->default('cash');
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_payments');
        Schema::dropIfExists('fines');
    }
};
