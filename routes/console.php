<?php

use App\Modules\Library\Application\Pdf\PdfProcessingManager;
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

Artisan::command('ebooks:pdf:process {--limit=5} {--ebook=}', function () {
    $limit = max(1, min(50, (int) $this->option('limit')));
    $ebook = $this->option('ebook');
    $ebookId = is_numeric($ebook) ? (int) $ebook : null;

    $result = app(PdfProcessingManager::class)->processPending(
        $limit,
        $ebookId,
    );

    $this->info(
        "Checked {$result['checked']} PDF source(s): "
        ."{$result['processed']} processed, {$result['failed']} failed.",
    );
})->purpose('Process pending ebook PDF metadata and first-page previews');

Schedule::command('ebooks:uploads:cleanup')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ebooks:external:verify')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ebooks:pdf:process --limit=5')
    ->everyMinute()
    ->withoutOverlapping();
