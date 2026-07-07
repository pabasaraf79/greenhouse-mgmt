<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\GreenhouseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ThresholdController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

/*
| Pages available to every authenticated user (admin + operator), per the
| documented role scope: dashboard, control panel, alerts, own profile.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::patch('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');

    Route::get('/control', [ControlController::class, 'index'])->name('control.index');
    Route::post('/control/toggle', [ControlController::class, 'toggle'])->name('control.toggle');
    Route::post('/control/rules/{key}/toggle', [ControlController::class, 'toggleRule'])->name('control.rule-toggle');

    // Breeze profile management.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
| Admin-only pages: fleet configuration, thresholds, schedules, reports.
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('greenhouses', GreenhouseController::class);

    Route::resource('devices', DeviceController::class);
    Route::post('devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])
        ->name('devices.regenerate-key');
    Route::get('devices/{device}/firmware', [DeviceController::class, 'downloadFirmware'])
        ->name('devices.firmware');

    Route::resource('thresholds', ThresholdController::class)->only(['index', 'update']);

    Route::resource('schedules', ScheduleController::class)->except(['show']);
    Route::post('schedules/{schedule}/run-now', [ScheduleController::class, 'runNow'])->name('schedules.run-now');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::post('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
});

require __DIR__.'/auth.php';
