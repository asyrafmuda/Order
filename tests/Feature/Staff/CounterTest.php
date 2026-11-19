<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->cashier = userWithRole(UserRole::Cashier);
});

function counterReadyOrder(array $options = []): Order
{
    return app(MarkOrderReady::class)->handle(placeOrder($options))->refresh();
}

it('sorts active orders into kitchen, ready and delivery columns', function () {
    $cooking = placeOrder();
    $ready = counterReadyOrder();
    $delivery = placeOrder(['type' => OrderType::Delivery]);
    $rider = userWithRole(UserRole::Rider);

    $this->actingAs($this->cashier)
        ->get(route('counter.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Counter/Index')
            ->has('kitchenOrders', 1)
            ->where('kitchenOrders.0.uuid', $cooking->uuid)
            ->has('readyOrders', 1)
            ->where('readyOrders.0.uuid', $ready->uuid)
            ->has('deliveryOrders', 1)
            ->where('deliveryOrders.0.uuid', $delivery->uuid)
            ->where('deliveryOrders.0.delivery.rider', null)
            ->has('riders', 1)
            ->where('riders.0.name', $rider->name)
            ->where('riders.0.open_deliveries', 0)
            ->where('stats.active', 3)
            ->where('stats.cash_orders', 2)
            ->where('stats.cash_to_collect', $cooking->total + $ready->total)
            ->where('stats.awaiting_rider', 1)
            ->where('stats.completed_today', 0)
            ->where('selected', null)
            ->where('lookupFailed', false));
});

it('opens the hand-over panel from the pickup code on the customer QR', function () {
    $order = counterReadyOrder();

    $this->actingAs($this->cashier)
        ->get(route('counter.index', ['code' => strtolower($order->pickup_code)]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected.uuid', $order->uuid)
            ->has('selected.items', 1)
            ->has('selected.histories'));

    $this->actingAs($this->cashier)
        ->get(route('counter.index', ['order' => $order->uuid]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected.order_number', $order->order_number));
});

it('says so when a scanned code matches nothing', function () {
    $this->actingAs($this->cashier)
        ->get(route('counter.index', ['code' => 'NOPE99']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected', null)
            ->where('lookupFailed', true));
});

it('finds an open order by pickup code or order number', function () {
    $order = placeOrder();

    $this->actingAs($this->cashier)
        ->post(route('counter.verify'), ['code' => ' '.strtolower($order->pickup_code)])
        ->assertRedirect(route('counter.index', ['order' => $order->uuid]));

    $this->actingAs($this->cashier)
        ->post(route('counter.verify'), ['code' => '#'.strtolower($order->order_number)])
        ->assertRedirect(route('counter.index', ['order' => $order->uuid]));
});

it('reports an unknown or finished pickup code', function () {
    $order = counterReadyOrder(['method' => PaymentMethod::Fpx]);
    $this->actingAs($this->cashier)->post(route('counter.orders.complete', $order));

    $this->actingAs($this->cashier)
        ->post(route('counter.verify'), ['code' => $order->pickup_code])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->cashier)
        ->post(route('counter.verify'), ['code' => 'NOPE99'])
        ->assertSessionHasErrors('code');
});

it('collects cash and then hands the order over', function () {
    $order = counterReadyOrder();

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.pay', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payments()->where('gateway', 'counter')->value('amount'))->toBe($order->total);

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.complete', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull();
});

it('refuses to hand over an unpaid order', function () {
    $order = counterReadyOrder();

    $this->actingAs($this->cashier)
        ->from(route('counter.index'))
        ->post(route('counter.orders.complete', $order))
        ->assertRedirect(route('counter.index'))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('refuses to hand over an order that is still cooking', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.complete', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('assigns, reassigns and unassigns a rider', function () {
    $order = placeOrder(['type' => OrderType::Delivery]);
    $rider = userWithRole(UserRole::Rider)->rider;

    $this->actingAs($this->cashier)
        ->put(route('counter.orders.rider', $order), ['rider_id' => $rider->id])
        ->assertSessionHas('success');

    expect($order->delivery->refresh()->rider_id)->toBe($rider->id)
        ->and($order->delivery->status)->toBe(DeliveryStatus::Assigned);

    $this->actingAs($this->cashier)
        ->get(route('counter.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('deliveryOrders.0.delivery.rider.id', $rider->id)
            ->where('riders.0.open_deliveries', 1)
            ->where('stats.awaiting_rider', 0));

    $this->actingAs($this->cashier)->put(route('counter.orders.rider', $order), ['rider_id' => null]);

    expect($order->delivery->refresh()->rider_id)->toBeNull()
        ->and($order->delivery->status)->toBe(DeliveryStatus::Pending);
});

it('validates the rider being assigned', function () {
    $order = placeOrder(['type' => OrderType::Delivery]);

    $this->actingAs($this->cashier)
        ->put(route('counter.orders.rider', $order), ['rider_id' => 999])
        ->assertSessionHasErrors('rider_id');
});

it('dispatches a ready delivery once a rider is assigned', function () {
    $order = counterReadyOrder(['type' => OrderType::Delivery]);
    $rider = userWithRole(UserRole::Rider)->rider;

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.dispatch', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);

    $this->actingAs($this->cashier)->put(route('counter.orders.rider', $order), ['rider_id' => $rider->id]);
    $this->actingAs($this->cashier)
        ->post(route('counter.orders.dispatch', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery)
        ->and($order->delivery->status)->toBe(DeliveryStatus::PickedUp);
});

it('will not dispatch a pickup order', function () {
    $order = counterReadyOrder();

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.dispatch', $order))
        ->assertSessionHas('error');
});

it('cancels an order with a reason and refunds it', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.cancel', $order), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->cashier)
        ->post(route('counter.orders.cancel', $order), ['reason' => 'Grill broke down'])
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('Grill broke down')
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded);
});
