<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('site.maintenance')->group(function (): void {
    Route::get('/', fn () => Inertia::render('Public/Home'))
        ->name('home');

    Route::get('/reader-preview', fn () => Inertia::render('Reader/Index'))
        ->name('reader.preview');
});

require __DIR__.'/admin.php';
