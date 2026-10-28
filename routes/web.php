<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard.index'));

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

// Endpoint JSON dashboard (di web.php agar memakai sesi login yang sama)
Route::prefix('api/dashboard')->name('api.dashboard.')->group(function () {
    Route::get('meta', [DashboardController::class, 'meta'])->name('meta');
    Route::get('charts/{id}', [DashboardController::class, 'chart'])->whereNumber('id')->name('chart');
});

// Import Excel
Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::post('/import', [ImportController::class, 'store'])->name('import.store');
Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show');
Route::get('/import/{batch}/status', [ImportController::class, 'status'])->name('import.status');
Route::get('/import/{batch}/preview', [ImportController::class, 'preview'])->name('import.preview');
Route::post('/import/{batch}/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
Route::delete('/import/{batch}', [ImportController::class, 'cancel'])->name('import.cancel');