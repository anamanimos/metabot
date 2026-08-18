<?php

use App\Http\Controllers\MetaAccountController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// Halaman Utama Redireksi ke Projects / Dashboard
Route::get('/', function () {
    return redirect()->route('projects.index');
});

// Routes Manajemen Project (Campaigns)
Route::resource('projects', ProjectController::class);
Route::post('projects/{id}/toggle', [ProjectController::class, 'toggleStatus'])->name('projects.toggle');
Route::post('projects/{id}/add-media', [ProjectController::class, 'addMedia'])->name('projects.addMedia');

// Routes Akun Meta
Route::resource('meta-accounts', MetaAccountController::class);

// Routes Dashboard Antrean Schedule
Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
Route::post('/schedules/bulk', [ScheduleController::class, 'storeBulk'])->name('schedules.storeBulk');
Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
Route::post('/schedules/sync-run', [ScheduleController::class, 'syncAndRunBot'])->name('schedules.syncRun');

// Route Fetch Portofolio Meta
Route::post('/portfolios/fetch', [PortfolioController::class, 'fetchFromMeta'])->name('portfolios.fetch');
