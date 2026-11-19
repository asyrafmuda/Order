<?php

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\KitchenStation;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->kitchen = userWithRole(UserRole::Kitchen);
});

it('shows the kitchen queue oldest first with station load', function () {
    $first = placeOrder(['products' => [menuItem(), menuItem()]]);
    $second = placeOrder();
    placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

    $this->actingAs($this->kitchen)
        ->get(route('kitchen.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Kitchen/Index')
            ->has('orders', 2)
            ->where('orders.0.uuid', $first->uuid)
            ->where('orders.1.uuid', $second->uuid)
            ->has('orders.0.items', 2)
            ->has('orders.0.items.0.station.name')
            ->has('ready', 0)
            ->has('stations', KitchenStation::query()->count())
            ->where('stations', fn ($stations) => collect($stations)->sum('queued') === 3
                && collect($stations)->firstWhere('id', $second->items->first()->kitchen_station_id)['queued'] === 1)
            ->where('filters.station', null));
});

it('filters the queue down to one station', function () {
    $grill = placeOrder();
    placeOrder();
    $stationId = $grill->items->first()->kitchen_station_id;

    $this->actingAs($this->kitchen)
        ->get(route('kitchen.index', ['station' => $stationId]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders', 1)
            ->where('orders.0.uuid', $grill->uuid)
            ->where('filters.station', $stationId));
});

it('lists orders that just became ready in their own strip', function () {
    $order = placeOrder();

    $this->actingAs($this->kitchen)->post(route('kitchen.orders.ready', $order));

    $this->actingAs($this->kitchen)
        ->get(route('kitchen.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders', 0)
            ->has('ready', 1)
            ->where('ready.0.uuid', $order->uuid));
});

it('moves the order to preparing and then ready as items are updated', function () {
    $order = placeOrder(['products' => [menuItem(), menuItem()]]);
    [$first, $second] = $order->items;

    $this->actingAs($this->kitchen)
        ->from(route('kitchen.index'))
        ->patch(route('kitchen.items.update', $first), ['status' => 'cooking'])
        ->assertRedirect(route('kitchen.index'));

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing)
        ->and($first->refresh()->kitchen_status)->toBe(KitchenItemStatus::Cooking)
        ->and($first->started_at)->not->toBeNull();

    $this->actingAs($this->kitchen)->patch(route('kitchen.items.update', $first), ['status' => 'done']);
    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $this->actingAs($this->kitchen)->patch(route('kitchen.items.update', $second), ['status' => 'done']);
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('rejects an unknown item status', function () {
    $order = placeOrder();

    $this->actingAs($this->kitchen)
        ->patch(route('kitchen.items.update', $order->items->first()), ['status' => 'burnt'])
        ->assertSessionHasErrors('status');
});

it('flashes an error when the order has already left the kitchen', function () {
    $order = placeOrder();
    $this->actingAs($this->kitchen)->post(route('kitchen.orders.ready', $order));

    $this->actingAs($this->kitchen)
        ->from(route('kitchen.index'))
        ->patch(route('kitchen.items.update', $order->items->first()), ['status' => 'cooking'])
        ->assertRedirect(route('kitchen.index'))
        ->assertSessionHas('error');
});

it('starts every queued item at once', function () {
    $order = placeOrder(['products' => [menuItem(), menuItem(), menuItem()]]);

    $this->actingAs($this->kitchen)
        ->post(route('kitchen.orders.start', $order))
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing)
        ->and($order->items->every(fn ($item) => $item->kitchen_status === KitchenItemStatus::Cooking))->toBeTrue();
});

it('marks the whole order ready', function () {
    $order = placeOrder(['products' => [menuItem(), menuItem()]]);

    $this->actingAs($this->kitchen)
        ->post(route('kitchen.orders.ready', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Ready)
        ->and($order->items->every(fn ($item) => $item->kitchen_status === KitchenItemStatus::Done))->toBeTrue()
        ->and($order->histories()->where('status', OrderStatus::Ready)->value('user_id'))->toBe($this->kitchen->id);
});
