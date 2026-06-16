<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Operator + Admin routes (operators and admins both allowed)
|--------------------------------------------------------------------------
| Placeholder routes for now; controllers/views are built in later phases.
*/
Route::middleware(['auth', 'role:operator'])->group(function () {
    Route::get('/control', fn () => 'Control Panel (operator + admin)')->name('control.index');
    Route::get('/alerts', fn () => 'Alerts (operator + admin)')->name('alerts.index');
});

/*
|--------------------------------------------------------------------------
| Admin-only routes (config, thresholds, devices)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/config', fn () => 'Configuration (admin only)')->name('config.index');
    Route::get('/thresholds', fn () => 'Thresholds (admin only)')->name('thresholds.index');
    Route::get('/devices', fn () => 'Devices (admin only)')->name('devices.index');
});

require __DIR__.'/auth.php';
