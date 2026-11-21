<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

/*
| The web installer and updater. Loaded with the "web" group (without the
| Inertia shared data, which needs a database) under /install.
*/

Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
Route::get('/license', [InstallController::class, 'license'])->name('license');
Route::post('/license', [InstallController::class, 'storeLicense'])->middleware('throttle:10,1')->name('license.store');
Route::get('/database', [InstallController::class, 'database'])->name('database');
Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
Route::post('/reconnect', [InstallController::class, 'reconnect'])->name('reconnect');
Route::get('/shop', [InstallController::class, 'shop'])->name('shop');
Route::post('/shop', [InstallController::class, 'install'])->name('run');
Route::get('/done', [InstallController::class, 'done'])->name('done');

Route::get('/update', [UpdateController::class, 'show'])->name('update');
Route::post('/update', [UpdateController::class, 'run'])->middleware('throttle:6,1')->name('update.run');
// The second half of a one-click update (Admin → System Update), on the new code.
Route::get('/update/finish/{token}', [UpdateController::class, 'finish'])->middleware('throttle:10,1')->name('update.finish');
