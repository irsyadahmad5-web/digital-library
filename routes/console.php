<?php

use App\Modules\Library\Application\Storage\ChunkUploadManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ebooks:uploads:cleanup', function () {
    $count = app(ChunkUploadManager::class)->cleanupAllExpired();

    $this->info("Cleaned {$count} expired ebook upload session(s).");
})->purpose('Remove expired ebook upload chunks and sessions');

Artisan::command('ebooks:external:verify', function () {
    $result = app(EbookFileManager::class)->reverifyDueExternalSources();

    $this->info(
        "Checked {$result['checked']} external source(s): "
        ."{$result['verified']} verified, {$result['failed']} failed.",
    );
})->purpose('Reverify due external ebook PDF sources');

Schedule::command('ebooks:uploads:cleanup')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ebooks:external:verify')
    ->hourly()
    ->withoutOverlapping();
