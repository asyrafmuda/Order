<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Events\OrderUpdated;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->main = Outlet::default();
    $this->branch = secondOutlet('Shah Alam');
});

it('shows each kitchen only its own tickets', function () {
    $mainOrder = placeOrder(['outlet' => $this->main]);
    $branchOrder = placeOrder(['outlet' => $this->branch]);

    $this->actingAs(staffAt($this->main, UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.uuid', $mainOrder->uuid)->where('outlet.current.id', $this->main->id));

    $this->actingAs(staffAt($this->branch, UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.uuid', $branchOrder->uuid)->where('outlet.current.name', 'Shah Alam')->where('outlet.can_switch', false));
});

it('treats staff without an outlet as working at the main outlet', function () {
    placeOrder(['outlet' => $this->main]);
    placeOrder(['outlet' => $this->branch]);
    placeOrder(['outlet' => $this->branch]);

    $this->actingAs(userWithRole(UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('outlet.current.id', $this->main->id));
});

it('ignores an outlet picked in the session once outlet staff sign in', function () {
    placeOrder(['outlet' => $this->branch]);

    // Browsed the other branch's menu as a customer first, then signed in to work.
    $this->put(route('outlets.choose'), ['outlet_id' => $this->branch->id]);

    $this->actingAs(staffAt($this->main, UserRole::Cashier))->get(route('counter.index'))
        ->assertInertia(fn (Assert $page) => $page->has('kitchenOrders', 0)->where('outlet.current.id', $this->main->id));
});

it('hides another outlet\'s tickets from the kitchen crew', function () {
    $order = placeOrder(['outlet' => $this->branch]);
    $this->actingAs(staffAt($this->main, UserRole::Kitchen));

    $this->post(route('kitchen.orders.start', $order))->assertNotFound();
    $this->post(route('kitchen.orders.ready', $order))->assertNotFound();
    $this->patch(route('kitchen.items.update', $order->items()->firstOrFail()), ['status' => 'cooking'])->assertNotFound();

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);

    $this->actingAs(staffAt($this->branch, UserRole::Kitchen))->post(route('kitchen.orders.ready', $order))->assertSessionHas('success');
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('keeps the counter to its own outlet\'s orders', function () {
    $order = placeOrder(['outlet' => $this->branch]);
    app(MarkOrderReady::class)->handle($order);
    $this->actingAs(staffAt($this->main, UserRole::Cashier));

    $this->get(route('counter.index'))->assertInertia(fn (Assert $page) => $page->has('readyOrders', 0)->has('kitchenOrders', 0));
    $this->get(route('counter.index', ['order' => $order->uuid]))->assertInertia(fn (Assert $page) => $page->where('selected', null)->where('lookupFailed', true));

    $this->post(route('counter.verify'), ['code' => $order->pickup_code])->assertSessionHasErrors('code');
    $this->post(route('counter.orders.pay', $order))->assertNotFound();
    $this->post(route('counter.orders.complete', $order))->assertNotFound();
    $this->post(route('counter.orders.cancel', $order), ['reason' => 'Mistake'])->assertNotFound();

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);

    $this->actingAs(staffAt($this->branch, UserRole::Cashier))
        ->post(route('counter.verify'), ['code' => $order->pickup_code])
        ->assertRedirect(route('counter.index', ['order' => $order->uuid]));
});

it('reads the same queue number at two outlets as two different orders', function () {
    $mainOrder = placeOrder(['outlet' => $this->main]);
    $branchOrder = placeOrder(['outlet' => $this->branch]);

    expect($mainOrder->order_number)->toBe($branchOrder->order_number);

    $this->actingAs(staffAt($this->branch, UserRole::Cashier))
        ->post(route('counter.verify'), ['code' => '#'.$branchOrder->order_number])
        ->assertRedirect(route('counter.index', ['order' => $branchOrder->uuid]));

    $this->actingAs(staffAt($this->main, UserRole::Cashier))
        ->post(route('counter.verify'), ['code' => '#'.$mainOrder->order_number])
        ->assertRedirect(route('counter.index', ['order' => $mainOrder->uuid]));
});

it('assigns only riders of the same outlet', function () {
    $mainRider = staffAt($this->main, UserRole::Rider)->rider;
    $branchRider = staffAt($this->branch, UserRole::Rider)->rider;
    $order = placeOrder(['type' => OrderType::Delivery, 'outlet' => $this->main]);
    $this->actingAs(staffAt($this->main, UserRole::Cashier));

    $this->get(route('counter.index'))->assertInertia(fn (Assert $page) => $page->has('riders', 1)->where('riders.0.id', $mainRider->id));

    $this->put(route('counter.orders.rider', $order), ['rider_id' => $branchRider->id])->assertSessionHasErrors('rider_id');
    expect($order->delivery->refresh()->rider_id)->toBeNull();

    $this->put(route('counter.orders.rider', $order), ['rider_id' => $mainRider->id])->assertSessionHas('success');
    expect($order->delivery->refresh()->status)->toBe(DeliveryStatus::Assigned);
});

it('lets a rider act only on deliveries of their outlet', function () {
    $branchRider = staffAt($this->branch, UserRole::Rider);
    $order = placeOrder(['type' => OrderType::Delivery, 'outlet' => $this->main]);
    app(MarkOrderReady::class)->handle($order);

    $this->actingAs($branchRider)->post(route('rider.deliveries.pickup', $order->delivery))->assertNotFound();
    $this->actingAs($branchRider)->get(route('rider.index'))->assertInertia(fn (Assert $page) => $page->has('deliveries', 0));
});

it('rings up counter sales at the cashier\'s own outlet', function () {
    $mainTable = DiningTable::factory()->create(['name' => 'Main 1']);
    $branchTable = DiningTable::factory()->create(['name' => 'Branch 1', 'outlet_id' => $this->branch->id]);
    $line = ['product_id' => menuItem()->id, 'quantity' => 1];
    $this->actingAs(staffAt($this->branch, UserRole::Cashier));

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->has('tables', 1)->where('tables.0.name', 'Branch 1'));

    $this->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $mainTable->id, 'payment_method' => 'cash', 'paid' => true, 'lines' => [$line]])
        ->assertSessionHasErrors('dining_table_id');

    $this->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $branchTable->id, 'payment_method' => 'cash', 'paid' => true, 'lines' => [$line]])
        ->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->outlet_id)->toBe($this->branch->id)
        ->and($order->table_label)->toBe('Branch 1')
        ->and($order->order_number)->toBe('A001');

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->has('recent', 1));
    $this->actingAs(staffAt($this->main, UserRole::Cashier))->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->has('recent', 0));
});

it('announces an order only on its own outlet\'s channels', function () {
    $order = placeOrder(['outlet' => $this->branch]);

    $channels = array_map(fn ($channel) => $channel->name, (new OrderUpdated($order))->broadcastOn());

    expect($channels)->toBe(["orders.{$order->uuid}", "shop.{$this->branch->id}", "private-staff.{$this->branch->id}"])
        ->and(staffAt($this->branch, UserRole::Kitchen)->worksIn($this->branch->id))->toBeTrue()
        ->and(staffAt($this->main, UserRole::Kitchen)->worksIn($this->branch->id))->toBeFalse()
        ->and(userWithRole(UserRole::Kitchen)->worksIn($this->main->id))->toBeTrue()
        ->and(userWithRole(UserRole::Admin)->worksIn($this->branch->id))->toBeTrue();
});

it('cancels unpaid orders and closes stale ones at every outlet', function () {
    $unpaid = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false, 'outlet' => $this->branch]);
    $stale = placeOrder(['outlet' => $this->branch]);
    app(MarkOrderReady::class)->handle($stale);

    $this->travel(2)->days();
    $this->artisan('orders:cancel-unpaid')->assertSuccessful();
    $this->artisan('orders:close-stale')->assertSuccessful();

    expect($unpaid->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($stale->refresh()->status)->not->toBe(OrderStatus::Ready);
});
