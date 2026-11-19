<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ComboOfferController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\KitchenStationController;
use App\Http\Controllers\Admin\ModifierGroupController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RiderController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin back office. Loaded with "web", "auth" and "role:admin", under the
| /admin prefix and the "admin." route name prefix.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{order}/resync', [OrderController::class, 'resync'])->name('orders.resync');

Route::resource('products', ProductController::class)->except('show');
Route::patch('/products/{product}/status', [ProductController::class, 'status'])->name('products.status');

$crud = ['index', 'store', 'update', 'destroy'];

Route::resource('categories', CategoryController::class)->only($crud);
Route::resource('modifier-groups', ModifierGroupController::class)->only($crud);
Route::resource('combo-offers', ComboOfferController::class)->only($crud);
Route::resource('kitchen-stations', KitchenStationController::class)->only($crud);
Route::resource('delivery-zones', DeliveryZoneController::class)->only($crud);
Route::resource('riders', RiderController::class)->only($crud);
Route::resource('users', UserController::class)->only($crud);

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
Route::post('/integrations/pos/test', [IntegrationController::class, 'testPos'])->name('integrations.pos.test');
Route::post('/integrations/pos/sync-menu', [IntegrationController::class, 'syncMenu'])->name('integrations.pos.sync-menu');

Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
