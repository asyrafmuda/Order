<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Actions\Kitchen\UpdateKitchenItem;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\TransitionOrder;
use App\Actions\Payments\CollectCashPayment;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Exceptions\OrderException;
use Illuminate\Support\Facades\Event;

it('sends a cash pickup order straight to the kitchen, unpaid', function () {
    $order = placeOrder();

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->order_number)->toBe('A001')
        ->and($order->estimated_ready_at)->not->toBeNull()
        ->and($order->items)->toHaveCount(1);
});

it('holds an online order until payment is confirmed', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

    expect($order->status)->toBe(OrderStatus::PendingPayment);

    $paid = placeOrder(['method' => PaymentMethod::Fpx]);

    expect($paid->status)->toBe(OrderStatus::Confirmed)
        ->and($paid->isPaid())->toBeTrue()
        ->and($paid->order_number)->toBe('A002');
});

it('pushes confirmed orders to the POS and messages the customer', function () {
    $order = placeOrder(['method' => PaymentMethod::Card]);

    expect($order->pos_sync_status)->toBe(SyncStatus::Synced)
        ->and($order->pos_receipt_number)->not->toBeNull()
        ->and($order->posSyncLogs)->toHaveCount(1)
        ->and($order->notificationLogs()->where('template', 'order_confirmed')->count())->toBe(1);
});

it('moves the order along as the kitchen works through its items', function () {
    $order = placeOrder(['products' => [menuItem(), menuItem()]]);
    [$first, $second] = $order->items;
    $update = app(UpdateKitchenItem::class);

    $update->handle($first, KitchenItemStatus::Cooking);
    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $update->handle($first, KitchenItemStatus::Done);
    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $update->handle($second, KitchenItemStatus::Done);
    expect($order->refresh()->status)->toBe(OrderStatus::Ready)
        ->and($order->ready_at)->not->toBeNull();
});

it('marks every item done when the kitchen marks the order ready', function () {
    $order = placeOrder(['quantity' => 2]);

    app(MarkOrderReady::class)->handle($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Ready)
        ->and($order->items->every(fn ($item) => $item->kitchen_status === KitchenItemStatus::Done))->toBeTrue();
});

it('will not hand over an unpaid order', function () {
    $order = placeOrder();
    app(MarkOrderReady::class)->handle($order);

    expect(fn () => app(CompleteOrder::class)->handle($order->refresh()))->toThrow(OrderException::class);

    app(CollectCashPayment::class)->handle($order, userWithRole(UserRole::Cashier));
    $completed = app(CompleteOrder::class)->handle($order->refresh());

    expect($completed->status)->toBe(OrderStatus::Completed)
        ->and($completed->isPaid())->toBeTrue();
});

it('rejects status changes the state machine does not allow', function () {
    $order = placeOrder();

    expect(fn () => app(TransitionOrder::class)->handle($order, OrderStatus::OutForDelivery))
        ->toThrow(OrderException::class);
});

it('refunds a paid order when it is cancelled', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    $cancelled = app(CancelOrder::class)->handle($order, 'Out of stock');

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($cancelled->cancel_reason)->toBe('Out of stock');
});

it('creates a delivery record with the zone fee for delivery orders', function () {
    $order = placeOrder(['type' => OrderType::Delivery]);

    expect($order->delivery)->not->toBeNull()
        ->and($order->delivery_fee)->toBe(500)
        ->and($order->total)->toBe($order->subtotal + 500);
});

it('announces every status change', function () {
    Event::fake([OrderStatusChanged::class]);

    placeOrder();

    Event::assertDispatched(OrderStatusChanged::class, fn ($event) => $event->to === OrderStatus::Confirmed);
});
