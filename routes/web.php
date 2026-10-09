<?php

use App\Http\Controllers\Installer\InstallerController;
use App\Http\Controllers\Operations\HealthController;
use App\Http\Controllers\Public\PublicDownloadController;
use App\Http\Controllers\Public\PublicLibraryController;
use App\Http\Controllers\Public\PublicReaderController;
use App\Http\Controllers\Public\PwaController;
use App\Http\Controllers\Public\SeoController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')
    ->middleware(['install.open', 'throttle:120,1,install-global-'])
    ->group(function (): void {
        Route::get('/', [InstallerController::class, 'index'])
            ->name('install.index');

        Route::post('/database', [InstallerController::class, 'database'])
            ->middleware('throttle:20,1,install-database-')
            ->name('install.database');

        Route::get('/admin', [InstallerController::class, 'admin'])
            ->name('install.admin');

        Route::post('/reconfigure', [InstallerController::class, 'reconfigure'])
            ->middleware('throttle:20,1,install-reconfigure-')
            ->name('install.reconfigure');

        Route::post('/complete', [InstallerController::class, 'complete'])
            ->middleware('throttle:10,1,install-complete-')
            ->name('install.complete');
    });

Route::get('/health/ready', [HealthController::class, 'ready'])
    ->middleware('throttle:120,1,health-ready-')
    ->name('health.ready');

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])
    ->name('pwa.manifest');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])
    ->name('seo.sitemap');

Route::get('/robots.txt', [SeoController::class, 'robots'])
    ->name('seo.robots');

$slugPattern = '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*';

Route::middleware('site.maintenance')->group(function () use ($slugPattern): void {
    Route::get('/', [PublicLibraryController::class, 'home'])
        ->name('home');

    Route::get('/library', [PublicLibraryController::class, 'library'])
        ->middleware('throttle:120,1,public-library-')
        ->name('library.index');

    Route::get('/search', [PublicLibraryController::class, 'library'])
        ->middleware('throttle:120,1,public-search-')
        ->name('library.search');

    Route::get('/categories', [PublicLibraryController::class, 'categories'])
        ->name('categories.index');

    Route::get('/authors', [PublicLibraryController::class, 'authors'])
        ->name('authors.index');

    Route::get('/publishers', [PublicLibraryController::class, 'publishers'])
        ->name('publishers.index');

    Route::get('/collections', [PublicLibraryController::class, 'collections'])
        ->name('collections.index');

    Route::get('/category/{slug}', [PublicLibraryController::class, 'category'])
        ->where('slug', $slugPattern)
        ->name('categories.show');

    Route::get('/author/{slug}', [PublicLibraryController::class, 'author'])
        ->where('slug', $slugPattern)
        ->name('authors.show');

    Route::get('/publisher/{slug}', [PublicLibraryController::class, 'publisher'])
        ->where('slug', $slugPattern)
        ->name('publishers.show');

    Route::get('/collection/{slug}', [PublicLibraryController::class, 'collection'])
        ->where('slug', $slugPattern)
        ->name('collections.show');

    Route::get('/book/{slug}', [PublicLibraryController::class, 'book'])
        ->where('slug', $slugPattern)
        ->name('books.show');

    Route::match(['GET', 'HEAD'], '/book/{slug}/download', [PublicDownloadController::class, 'download'])
        ->where('slug', $slugPattern)
        ->name('books.download');

    Route::get('/read/{slug}', [PublicReaderController::class, 'show'])
        ->where('slug', $slugPattern)
        ->name('reader.show');

    Route::match(['GET', 'HEAD'], '/read/{slug}/file', [PublicReaderController::class, 'source'])
        ->where('slug', $slugPattern)
        ->name('reader.source');

    Route::get('/about', [PublicLibraryController::class, 'about'])
        ->name('about');

    Route::get('/contact', [PublicLibraryController::class, 'contact'])
        ->name('contact');
});

require __DIR__.'/admin.php';

Route::fallback([PublicLibraryController::class, 'notFound'])
    ->middleware('site.maintenance');
