<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Support\Settings;

function readyOrder(array $options, int $hoursAgo): Order
{
    $order = placeOrder($options);
    app(MarkOrderReady::class)->handle($order);
    $order->forceFill(['ready_at' => now()->subHours($hoursAgo)])->save();

    return $order;
}

it('completes paid orders nobody marked as collected and cancels unpaid no-shows', function () {
    $paid = readyOrder(['method' => PaymentMethod::Fpx], hoursAgo: 7);
    $noShow = readyOrder([], hoursAgo: 7);
    $recent = readyOrder(['method' => PaymentMethod::Fpx], hoursAgo: 1);

    $this->artisan('orders:close-stale')->assertSuccessful();

    expect($paid->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($noShow->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($noShow->cancel_reason)->toBe('Not collected')
        ->and($recent->refresh()->status)->toBe(OrderStatus::Ready);
});

it('leaves deliveries to the rider', function () {
    $delivery = readyOrder(['type' => OrderType::Delivery], hoursAgo: 24);

    $this->artisan('orders:close-stale');

    expect($delivery->refresh()->status)->toBe(OrderStatus::Ready);
});

it('can be switched off', function () {
    app(Settings::class)->put(['ordering.auto_close_hours' => 0]);
    $order = readyOrder(['method' => PaymentMethod::Fpx], hoursAgo: 48);

    $this->artisan('orders:close-stale')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});
