<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebooks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->string('subtitle', 220)->nullable();
            $table->string('slug', 240)->unique();
            $table->string('isbn', 20)->nullable()->unique();
            $table->longText('description')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('edition', 80)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('cover_path')->nullable();

            $table->foreignId('publisher_id')->nullable()->constrained('publishers')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();

            $table->string('publication_status', 24)->default('draft')->index();
            $table->boolean('read_enabled')->default(true)->index();
            $table->boolean('download_enabled')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['publication_status', 'published_at']);
            $table->index(['title', 'publication_status']);
            $table->index(['publisher_id', 'publication_status']);
            $table->index(['language_id', 'publication_status']);
            $table->index(['collection_id', 'publication_status']);
        });

        Schema::create('ebook_author', function (Blueprint $table) {
            $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['ebook_id', 'author_id']);
            $table->index(['author_id', 'ebook_id']);
        });

        Schema::create('category_ebook', function (Blueprint $table) {
            $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['ebook_id', 'category_id']);
            $table->index(['category_id', 'ebook_id']);
        });

        Schema::create('ebook_tag', function (Blueprint $table) {
            $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['ebook_id', 'tag_id']);
            $table->index(['tag_id', 'ebook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_tag');
        Schema::dropIfExists('category_ebook');
        Schema::dropIfExists('ebook_author');
        Schema::dropIfExists('ebooks');
    }
};
