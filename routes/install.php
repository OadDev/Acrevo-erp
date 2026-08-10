<?php

use App\Http\Controllers\InstallController;
use App\Http\Middleware\EnsureInstallerIsOpen;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The installer has to work before the database (or even .env) exists, so
// it can't depend on sessions, CSRF, or cookie encryption — all of those
// either need a database or an APP_KEY that may not exist yet. Access is
// instead gated by EnsureInstallerIsOpen (a one-time token read over SSH,
// plus a lock file once installation finishes).
Route::withoutMiddleware([
    EncryptCookies::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
])->middleware(EnsureInstallerIsOpen::class)->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');

    Route::get('/database', [InstallController::class, 'showDatabase'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.save');

    Route::get('/migrate', [InstallController::class, 'showMigrate'])->name('migrate');
    Route::post('/migrate', [InstallController::class, 'runMigrate'])->name('migrate.run');

    Route::get('/admin', [InstallController::class, 'showAdmin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'saveAdmin'])->name('admin.save');

    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});
