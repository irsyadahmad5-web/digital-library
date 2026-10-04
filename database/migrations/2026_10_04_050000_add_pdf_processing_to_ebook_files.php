<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebook_files', function (Blueprint $table) {
            $table->string('processing_status', 24)
                ->default('pending')
                ->index()
                ->after('verification_status');
            $table->unsignedInteger('page_count')
                ->nullable()
                ->after('processing_status');
            $table->json('pdf_metadata')
                ->nullable()
                ->after('page_count');
            $table->string('preview_path')
                ->nullable()
                ->after('pdf_metadata');
            $table->timestamp('processing_started_at')
                ->nullable()
                ->after('preview_path');
            $table->timestamp('processed_at')
                ->nullable()
                ->index()
                ->after('processing_started_at');
            $table->text('processing_error')
                ->nullable()
                ->after('processed_at');

            $table->index(['processing_status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ebook_files', function (Blueprint $table) {
            $table->dropIndex(['processing_status', 'updated_at']);
            $table->dropIndex(['processed_at']);
            $table->dropIndex(['processing_status']);
            $table->dropColumn([
                'processing_status',
                'page_count',
                'pdf_metadata',
                'preview_path',
                'processing_started_at',
                'processed_at',
                'processing_error',
            ]);
        });
    }
};
