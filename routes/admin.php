<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Ebooks\EbookController;
use App\Http\Controllers\Admin\Ebooks\Storage\EbookPdfProcessingController;
use App\Http\Controllers\Admin\Ebooks\Storage\EbookStorageController;
use App\Http\Controllers\Admin\Ebooks\Storage\EbookUploadController;
use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\Settings\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('admin')
    ->middleware('admin.headers')
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->name('admin.login.store');

            Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

            Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('password.email');

            Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

            Route::post('/reset-password', [NewPasswordController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('password.store');
        });

        Route::middleware(['auth', 'active', 'permission:admin.access'])->group(function (): void {
            Route::get('/', fn () => Inertia::render('Admin/Dashboard'))
                ->name('admin.dashboard');

            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('admin.logout');

            Route::get('/profile', [ProfileController::class, 'edit'])
                ->name('admin.profile.edit');

            Route::patch('/profile', [ProfileController::class, 'update'])
                ->name('admin.profile.update');

            Route::put('/password', [PasswordController::class, 'update'])
                ->name('admin.password.update');

            Route::delete('/sessions/others', [SessionController::class, 'destroyOthers'])
                ->name('admin.sessions.destroy-others');

            Route::get('/audit-log', [AuditLogController::class, 'index'])
                ->middleware('permission:admin.view-audit')
                ->name('admin.audit.index');

            Route::middleware('permission:library.manage-ebooks')
                ->prefix('ebooks')
                ->group(function (): void {
                    Route::get('/', [EbookController::class, 'index'])
                        ->name('admin.ebooks.index');

                    Route::get('/create', [EbookController::class, 'create'])
                        ->name('admin.ebooks.create');

                    Route::post('/bulk', [EbookController::class, 'bulk'])
                        ->name('admin.ebooks.bulk');

                    Route::post('/', [EbookController::class, 'store'])
                        ->name('admin.ebooks.store');

                    Route::post('/{id}/storage/external', [EbookStorageController::class, 'external'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.storage.external');

                    Route::delete('/{id}/storage', [EbookStorageController::class, 'destroy'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.storage.destroy');

                    Route::post('/{id}/processing', [EbookPdfProcessingController::class, 'store'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.processing.store');

                    Route::post('/{id}/uploads', [EbookUploadController::class, 'start'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.uploads.start');

                    Route::get('/{id}/uploads/{session}', [EbookUploadController::class, 'status'])
                        ->whereNumber('id')
                        ->whereUuid('session')
                        ->name('admin.ebooks.uploads.status');

                    Route::post('/{id}/uploads/{session}/chunks/{index}', [EbookUploadController::class, 'chunk'])
                        ->whereNumber('id')
                        ->whereUuid('session')
                        ->whereNumber('index')
                        ->name('admin.ebooks.uploads.chunk');

                    Route::post('/{id}/uploads/{session}/complete', [EbookUploadController::class, 'complete'])
                        ->whereNumber('id')
                        ->whereUuid('session')
                        ->name('admin.ebooks.uploads.complete');

                    Route::delete('/{id}/uploads/{session}', [EbookUploadController::class, 'destroy'])
                        ->whereNumber('id')
                        ->whereUuid('session')
                        ->name('admin.ebooks.uploads.destroy');

                    Route::get('/{id}/edit', [EbookController::class, 'edit'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.edit');

                    Route::put('/{id}', [EbookController::class, 'update'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.update');

                    Route::delete('/{id}', [EbookController::class, 'destroy'])
                        ->whereNumber('id')
                        ->name('admin.ebooks.destroy');
                });

            Route::middleware('permission:library.manage-master-data')
                ->prefix('master-data')
                ->group(function (): void {
                    Route::get('/', [MasterDataController::class, 'redirect'])
                        ->name('admin.master-data.redirect');

                    Route::post('/{entity}/bulk', [MasterDataController::class, 'bulk'])
                        ->name('admin.master-data.bulk');

                    Route::get('/{entity}', [MasterDataController::class, 'index'])
                        ->name('admin.master-data.index');

                    Route::post('/{entity}', [MasterDataController::class, 'store'])
                        ->name('admin.master-data.store');

                    Route::put('/{entity}/{id}', [MasterDataController::class, 'update'])
                        ->whereNumber('id')
                        ->name('admin.master-data.update');

                    Route::delete('/{entity}/{id}', [MasterDataController::class, 'destroy'])
                        ->whereNumber('id')
                        ->name('admin.master-data.destroy');
                });

            Route::get('/settings/{group?}', [SettingsController::class, 'index'])
                ->middleware('permission:admin.manage-settings')
                ->name('admin.settings.index');

            Route::post('/settings/{group}', [SettingsController::class, 'update'])
                ->middleware('permission:admin.manage-settings')
                ->name('admin.settings.update');
        });
    });
