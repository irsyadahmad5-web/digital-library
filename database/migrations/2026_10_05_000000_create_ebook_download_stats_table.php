<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebook_download_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebook_id')
                ->unique()
                ->constrained('ebooks')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['downloads', 'last_downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_download_stats');
    }
};
