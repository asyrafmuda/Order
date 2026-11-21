<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ComboOfferController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\DiningTableController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\KitchenStationController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\ModifierGroupController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OutletController;
use App\Http\Controllers\Admin\OutletPriceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RiderController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShopQrController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin back office. Loaded with "web", "auth", "role:admin,manager" and
| "outlet", under the /admin prefix and the "admin." route name prefix.
|
| Outlet managers run their own branch: its orders, tables, delivery areas,
| riders, reports and details. Everything shared by the whole business (the
| menu, staff accounts, settings, the list of outlets) is for super admins.
*/

$crud = ['index', 'store', 'update', 'destroy'];

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{order}/resync', [OrderController::class, 'resync'])->name('orders.resync');

Route::resource('delivery-zones', DeliveryZoneController::class)->only($crud);
Route::resource('riders', RiderController::class)->only($crud);

// The outlet's own QR code for the counter or kiosk.
Route::get('/shop-qr', [ShopQrController::class, 'print'])->name('shop-qr.print');
Route::post('/shop-qr/regenerate', [ShopQrController::class, 'regenerate'])->name('shop-qr.regenerate');

Route::get('/tables/print', [DiningTableController::class, 'print'])->name('tables.print');
Route::post('/tables/{table}/regenerate', [DiningTableController::class, 'regenerate'])->name('tables.regenerate');
Route::resource('tables', DiningTableController::class)->only($crud);

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

// The outlet being managed: its address, hours and ways of ordering.
Route::get('/outlet', [OutletController::class, 'edit'])->name('outlet.edit');
Route::put('/outlet', [OutletController::class, 'updateCurrent'])->name('outlet.update');

Route::middleware('role:admin')->group(function () use ($crud) {
    Route::resource('products', ProductController::class)->except('show');
    Route::patch('/products/{product}/status', [ProductController::class, 'status'])->name('products.status');

    Route::resource('categories', CategoryController::class)->only($crud);
    Route::resource('modifier-groups', ModifierGroupController::class)->only($crud);
    Route::resource('combo-offers', ComboOfferController::class)->only($crud);
    Route::resource('kitchen-stations', KitchenStationController::class)->only($crud);
    Route::resource('users', UserController::class)->only($crud);

    Route::post('/outlets/switch', [OutletController::class, 'switch'])->name('outlets.switch');
    Route::resource('outlets', OutletController::class)->only($crud);

    // What the outlet being viewed charges, where it differs from the standard price.
    Route::get('/outlet-prices', [OutletPriceController::class, 'edit'])->name('outlet-prices.edit');
    Route::put('/outlet-prices', [OutletPriceController::class, 'update'])->name('outlet-prices.update');

    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/pos/test', [IntegrationController::class, 'testPos'])->name('integrations.pos.test');
    Route::post('/integrations/pos/sync-menu', [IntegrationController::class, 'syncMenu'])->name('integrations.pos.sync-menu');

    // This copy's license (stays reachable while the site is locked).
    Route::get('/license', [LicenseController::class, 'show'])->name('license.show');
    Route::post('/license', [LicenseController::class, 'activate'])->middleware('throttle:10,1')->name('license.activate');
    Route::post('/license/check', [LicenseController::class, 'check'])->middleware('throttle:10,1')->name('license.check');
    Route::delete('/license', [LicenseController::class, 'release'])->name('license.release');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/logo', [SettingController::class, 'storeLogo'])->name('settings.logo.store');
    Route::delete('/settings/logo', [SettingController::class, 'destroyLogo'])->name('settings.logo.destroy');
});
