<?php

use App\Http\Controllers\Public\PublicDownloadController;
use App\Http\Controllers\Public\PublicLibraryController;
use App\Http\Controllers\Public\PublicReaderController;
use Illuminate\Support\Facades\Route;

Route::middleware('site.maintenance')->group(function (): void {
    Route::get('/', [PublicLibraryController::class, 'home'])
        ->name('home');

    Route::get('/library', [PublicLibraryController::class, 'library'])
        ->name('library.index');

    Route::get('/search', [PublicLibraryController::class, 'library'])
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
        ->name('categories.show');

    Route::get('/author/{slug}', [PublicLibraryController::class, 'author'])
        ->name('authors.show');

    Route::get('/publisher/{slug}', [PublicLibraryController::class, 'publisher'])
        ->name('publishers.show');

    Route::get('/collection/{slug}', [PublicLibraryController::class, 'collection'])
        ->name('collections.show');

    Route::get('/book/{slug}', [PublicLibraryController::class, 'book'])
        ->name('books.show');

    Route::match(['GET', 'HEAD'], '/book/{slug}/download', [PublicDownloadController::class, 'download'])
        ->name('books.download');

    Route::get('/read/{slug}', [PublicReaderController::class, 'show'])
        ->name('reader.show');

    Route::match(['GET', 'HEAD'], '/read/{slug}/file', [PublicReaderController::class, 'source'])
        ->name('reader.source');

    Route::get('/about', [PublicLibraryController::class, 'about'])
        ->name('about');

    Route::get('/contact', [PublicLibraryController::class, 'contact'])
        ->name('contact');
});

require __DIR__.'/admin.php';

Route::fallback([PublicLibraryController::class, 'notFound'])
    ->middleware('site.maintenance');
