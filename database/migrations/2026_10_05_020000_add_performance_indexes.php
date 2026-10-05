<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebooks', function (Blueprint $table): void {
            $table->index(
                ['publication_status', 'published_at', 'id'],
                'idx_ebooks_public_feed',
            );
            $table->index(
                ['publication_status', 'publication_year', 'title'],
                'idx_ebooks_status_year_title',
            );
            $table->index(
                ['updated_at', 'id'],
                'idx_ebooks_updated_id',
            );
        });

        Schema::table('ebook_files', function (Blueprint $table): void {
            $table->index(
                ['verification_status', 'processing_status', 'ebook_id'],
                'idx_ebook_files_public_ready',
            );
            $table->index(
                ['source_type', 'last_checked_at'],
                'idx_ebook_files_external_due',
            );
        });

        Schema::table('ebook_upload_sessions', function (Blueprint $table): void {
            $table->index(
                ['status', 'expires_at'],
                'idx_ebook_upload_sessions_status_expiry',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ebook_upload_sessions', function (Blueprint $table): void {
            $table->dropIndex('idx_ebook_upload_sessions_status_expiry');
        });

        Schema::table('ebook_files', function (Blueprint $table): void {
            $table->dropIndex('idx_ebook_files_public_ready');
            $table->dropIndex('idx_ebook_files_external_due');
        });

        Schema::table('ebooks', function (Blueprint $table): void {
            $table->dropIndex('idx_ebooks_public_feed');
            $table->dropIndex('idx_ebooks_status_year_title');
            $table->dropIndex('idx_ebooks_updated_id');
        });
    }
};
