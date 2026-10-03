<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Public/Home'))
    ->name('home');

Route::get('/reader-preview', fn () => Inertia::render('Reader/Index'))
    ->name('reader.preview');

Route::get('/admin-preview', fn () => Inertia::render('Admin/Dashboard'))
    ->name('admin.preview');