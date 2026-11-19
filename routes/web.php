<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderHistoryController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartPreferenceController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\KitchenStatusController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Webhooks\LoyverseWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', MenuController::class)->name('menu.index');
Route::get('/kitchen-status', KitchenStatusController::class)->name('status.index');

Route::prefix('cart')->name('cart.')->group(function () {
    Route::put('/preferences', CartPreferenceController::class)->name('preferences');
    Route::post('/', [CartController::class, 'store'])->name('store');
    Route::delete('/', [CartController::class, 'clear'])->name('clear');
    Route::patch('/{line}', [CartController::class, 'update'])->name('update');
    Route::post('/{line}/combo', [CartController::class, 'combo'])->name('combo');
    Route::delete('/{line}', [CartController::class, 'destroy'])->name('destroy');
});

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('checkout.store');

/*
| Orders are addressed by UUID, which is what lets a guest open their ticket.
*/
Route::get('/track', [OrderLookupController::class, 'index'])->name('orders.track');
Route::post('/track', [OrderLookupController::class, 'find'])
    ->middleware('throttle:10,1')
    ->name('orders.lookup');

Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])->name('orders.pay');
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

/*
|--------------------------------------------------------------------------
| Payments & webhooks
|--------------------------------------------------------------------------
*/

Route::get('/payments/fake/{payment}', [PaymentController::class, 'showFake'])
    ->middleware('signed')
    ->name('payments.fake.show');
Route::post('/payments/fake/{payment}', [PaymentController::class, 'completeFake'])->name('payments.fake.complete');
Route::match(['get', 'post'], '/payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');

Route::post('/webhooks/loyverse', LoyverseWebhookController::class)->name('webhooks.loyverse');

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/orders', OrderHistoryController::class)->name('orders');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', PasswordController::class)->name('password.update');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
});
