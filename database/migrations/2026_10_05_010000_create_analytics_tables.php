<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->unique();
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('home_views')->default(0);
            $table->unsignedBigInteger('catalog_views')->default(0);
            $table->unsignedBigInteger('directory_views')->default(0);
            $table->unsignedBigInteger('book_views')->default(0);
            $table->unsignedBigInteger('reader_opens')->default(0);
            $table->unsignedBigInteger('info_views')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();

            $table->index(['metric_date', 'page_views']);
        });

        Schema::create('ebook_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->foreignId('ebook_id')
                ->constrained('ebooks')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('detail_views')->default(0);
            $table->unsignedBigInteger('reader_opens')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'ebook_id']);
            $table->index(['ebook_id', 'metric_date']);
            $table->index(['metric_date', 'downloads']);
            $table->index(['metric_date', 'reader_opens']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_daily_metrics');
        Schema::dropIfExists('site_daily_metrics');
    }
};
