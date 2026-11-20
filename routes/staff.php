<?php

use App\Http\Controllers\Counter\CounterController;
use App\Http\Controllers\Counter\CounterOrderController;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Http\Controllers\Kitchen\KitchenItemController;
use App\Http\Controllers\Kitchen\KitchenOrderController;
use App\Http\Controllers\Kitchen\KitchenStockController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Rider\RiderDeliveryController;
use Illuminate\Support\Facades\Route;

/*
| Staff workspaces. Loaded with the "web" and "auth" middleware; each group
| is limited to the roles that work that station.
*/

Route::middleware('role:admin,manager,kitchen,cashier,waiter')->prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', KitchenController::class)->name('index');
    Route::patch('/items/{item}', [KitchenItemController::class, 'update'])->name('items.update');
    Route::post('/orders/{order}/start', [KitchenOrderController::class, 'start'])->name('orders.start');
    Route::post('/orders/{order}/ready', [KitchenOrderController::class, 'ready'])->name('orders.ready');
    Route::patch('/products/{product}/status', [KitchenStockController::class, 'update'])->name('products.status');
});

Route::middleware('role:admin,manager,cashier,waiter')->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/orders', [PosController::class, 'store'])->name('orders.store');
});

Route::middleware('role:admin,manager,cashier,waiter')->prefix('counter')->name('counter.')->group(function () {
    Route::get('/', CounterController::class)->name('index');
    Route::post('/verify', [CounterOrderController::class, 'verify'])->name('verify');
    Route::post('/orders/{order}/pay', [CounterOrderController::class, 'pay'])->name('orders.pay');
    Route::post('/orders/{order}/complete', [CounterOrderController::class, 'complete'])->name('orders.complete');
    Route::put('/orders/{order}/rider', [CounterOrderController::class, 'rider'])->name('orders.rider');
    Route::post('/orders/{order}/dispatch', [CounterOrderController::class, 'dispatch'])->name('orders.dispatch');
    Route::post('/orders/{order}/cancel', [CounterOrderController::class, 'cancel'])->name('orders.cancel');
});

Route::middleware('role:rider')->prefix('rider')->name('rider.')->group(function () {
    Route::get('/', RiderController::class)->name('index');
    Route::put('/availability', [RiderController::class, 'availability'])->name('availability');
    Route::post('/deliveries/{delivery}/pickup', [RiderDeliveryController::class, 'pickup'])->name('deliveries.pickup');
    Route::post('/deliveries/{delivery}/deliver', [RiderDeliveryController::class, 'deliver'])->name('deliveries.deliver');
});
