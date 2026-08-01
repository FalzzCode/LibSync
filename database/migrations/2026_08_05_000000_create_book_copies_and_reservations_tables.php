<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('inventory_code')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('condition')->default('good');
            $table->string('status')->default('available');
            $table->date('acquired_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status']);
        });

        Schema::create('book_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('waiting');
            $table->unsignedSmallInteger('queue_position')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reservations');
        Schema::dropIfExists('book_copies');
    }
};
