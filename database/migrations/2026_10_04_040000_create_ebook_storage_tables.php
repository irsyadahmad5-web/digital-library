<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebook_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebook_id')->unique()->constrained('ebooks')->cascadeOnDelete();
            $table->string('source_type', 24)->index();
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->string('verification_status', 24)->default('verified')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'verification_status']);
        });

        Schema::create('ebook_upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->string('status', 24)->default('uploading')->index();
            $table->char('expected_sha256', 64)->nullable();
            $table->char('computed_sha256', 64)->nullable();
            $table->string('temp_directory');
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['ebook_id', 'user_id', 'status']);
        });

        Schema::create('ebook_upload_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('upload_session_id')
                ->constrained('ebook_upload_sessions')
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->unique(['upload_session_id', 'chunk_index']);
            $table->index(['upload_session_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_upload_chunks');
        Schema::dropIfExists('ebook_upload_sessions');
        Schema::dropIfExists('ebook_files');
    }
};
