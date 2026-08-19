<?php

use App\Http\Controllers\MetaAccountController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('projects.index');
});

// Projects Management Routes
Route::resource('projects', ProjectController::class);
Route::post('projects/{id}/toggle-status', [ProjectController::class, 'toggleStatus'])->name('projects.toggleStatus');
Route::post('projects/{id}/add-media', [ProjectController::class, 'addMedia'])->name('projects.addMedia');

// Schedules Routes
Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
Route::delete('schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
Route::post('schedules/sync-run', [ScheduleController::class, 'syncAndRunBot'])->name('schedules.syncRun');
Route::get('schedules/execution-progress', [ScheduleController::class, 'executionProgress'])->name('schedules.executionProgress');

// Meta Accounts Routes
Route::resource('meta-accounts', MetaAccountController::class);
Route::get('meta-accounts/{id}/check-status', [MetaAccountController::class, 'checkStatus'])->name('meta-accounts.checkStatus');
Route::post('meta-accounts/{id}/import-state', [MetaAccountController::class, 'importState'])->name('meta-accounts.importState');
Route::post('meta-accounts/{id}/fetch-portfolios', [MetaAccountController::class, 'fetchPortfolios'])->name('meta-accounts.fetchPortfolios');

// Portfolios Scanning Route
Route::post('portfolios/fetch', [PortfolioController::class, 'fetchPortfolios'])->name('portfolios.fetch');
