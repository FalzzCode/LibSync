<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('book_code')->nullable()->after('id');
            $table->string('isbn')->nullable()->after('author');
            $table->string('shelf')->nullable()->after('category_id');
            $table->string('language', 50)->nullable()->after('shelf');
            $table->unsignedSmallInteger('page_count')->nullable()->after('language');
            $table->text('description')->nullable()->after('page_count');
            $table->timestamp('archived_at')->nullable()->after('cover_image');
            $table->index('book_code');
            $table->index('isbn');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['book_code']);
            $table->dropIndex(['isbn']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['book_code', 'isbn', 'shelf', 'language', 'page_count', 'description', 'archived_at']);
        });
    }
};
