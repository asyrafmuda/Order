<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Support\OutletContext;
use App\Support\OutletStock;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

it('runs as a single outlet until multi-outlet is switched on', function () {
    Outlet::factory()->create();

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('outlet.multi', false)
        ->where('outlet.can_switch', false)
        ->where('outlet.current.id', Outlet::default()->id)
        ->has('outlet.options', 0));

    $this->put(route('outlets.choose'), ['outlet_id' => Outlet::default()->id])->assertNotFound();

    expect(placeOrder()->outlet_id)->toBe(Outlet::default()->id);
});

it('lets a customer choose which outlet to order from', function () {
    $main = Outlet::default();
    $branch = secondOutlet('Shah Alam');
    app(Settings::class)->put(['shop.address' => '5 Jalan Plumbum, Shah Alam', 'shop.counter' => 'Kaunter 1'], $branch);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('outlet.multi', true)
        ->where('outlet.can_switch', true)
        ->where('outlet.current.id', $main->id)
        ->has('outlet.options', 2));

    $this->put(route('outlets.choose'), ['outlet_id' => $branch->id])->assertSessionHas('success');

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('outlet.current.name', 'Shah Alam')
        ->where('shop.address', '5 Jalan Plumbum, Shah Alam'));

    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $this->post(route('checkout.store'), ['customer_name' => 'Aina', 'customer_phone' => '0123456789', 'payment_method' => 'cash']);

    $order = Order::query()->sole();
    expect($order->outlet_id)->toBe($branch->id);

    // The ticket tells the customer where to go: the order's outlet, whatever they browse later.
    $this->put(route('outlets.choose'), ['outlet_id' => $main->id]);
    $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
        ->where('order.outlet.name', 'Shah Alam')
        ->where('order.outlet.address', '5 Jalan Plumbum, Shah Alam')
        ->where('order.outlet.counter', 'Kaunter 1'));
});

it('does not offer inactive outlets to customers', function () {
    $closed = secondOutlet();
    $closed->update(['is_active' => false]);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->has('outlet.options', 1));
    $this->put(route('outlets.choose'), ['outlet_id' => $closed->id])->assertSessionHasErrors('outlet_id');
});

it('numbers orders separately in each outlet', function () {
    $main = Outlet::default();
    $branch = secondOutlet();

    expect(placeOrder(['outlet' => $main])->order_number)->toBe('A001')
        ->and(placeOrder(['outlet' => $branch])->order_number)->toBe('A001')
        ->and(placeOrder(['outlet' => $branch])->order_number)->toBe('A002')
        ->and(placeOrder(['outlet' => $main])->order_number)->toBe('A002');
});

it('keeps each outlet\'s opening hours and ways of ordering to itself', function () {
    $branch = secondOutlet();
    app(Settings::class)->put(['ordering.enabled' => false, 'ordering.delivery_enabled' => false], $branch);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('shop.is_open', true)->where('shop.delivery_enabled', true));

    $this->put(route('outlets.choose'), ['outlet_id' => $branch->id]);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('shop.is_open', false)->where('shop.delivery_enabled', false));

    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $this->post(route('checkout.store'), ['customer_name' => 'Aina', 'customer_phone' => '0123456789', 'payment_method' => 'cash'])
        ->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0);
});

it('sells out an item in one outlet without touching the others', function () {
    $branch = secondOutlet();
    $product = menuItem();
    app(OutletStock::class)->mark($product, true, $branch);

    $this->post(route('cart.store'), ['product_id' => $product->id])->assertSessionMissing('error');
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.count', 1)
        ->where('categories.0.products.0.is_orderable', true));

    // The same ticket at the other outlet: the item drops off, and cannot be added.
    $this->put(route('outlets.choose'), ['outlet_id' => $branch->id]);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.count', 0)
        ->where('categories.0.products.0.is_orderable', false)
        ->where('categories.0.products.0.sold_out_here', true)
        ->where('categories.0.products.0.status', ProductStatus::Available->value));

    $this->post(route('cart.store'), ['product_id' => $product->id])->assertSessionHas('error');
});

it('offers only the delivery areas of the chosen outlet', function () {
    $branch = secondOutlet();
    $mainZone = DeliveryZone::factory()->create(['name' => 'Main zone', 'fee' => 500]);
    $branchZone = DeliveryZone::factory()->create(['name' => 'Branch zone', 'fee' => 900, 'outlet_id' => $branch->id]);
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 3000])->id]);
    $this->put(route('cart.preferences'), ['type' => 'delivery']);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->has('zones', 1)->where('cart.zone.name', 'Main zone')->where('cart.delivery_fee', 500));
    $this->put(route('cart.preferences'), ['zone_id' => $branchZone->id])->assertSessionHasErrors('zone_id');

    $this->put(route('outlets.choose'), ['outlet_id' => $branch->id]);
    $this->put(route('cart.preferences'), ['type' => 'delivery']);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->has('zones', 1)->where('cart.zone.name', 'Branch zone')->where('cart.delivery_fee', 900));
    $this->put(route('cart.preferences'), ['zone_id' => $mainZone->id])->assertSessionHasErrors('zone_id');
});

it('seats the customer in the outlet whose table QR they scanned', function () {
    $branch = secondOutlet('Shah Alam');
    $table = DiningTable::factory()->create(['name' => 'Table 9', 'outlet_id' => $branch->id]);

    $this->get(route('tables.show', $table->token))->assertSessionHas('success');

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('outlet.current.name', 'Shah Alam')
        ->where('cart.table.name', 'Table 9'));

    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $this->post(route('checkout.store'), ['payment_method' => 'cash']);

    $order = Order::query()->sole();
    expect($order->outlet_id)->toBe($branch->id)->and($order->table_label)->toBe('Table 9');

    // Choosing another outlet means they are no longer ordering for that table.
    $this->put(route('outlets.choose'), ['outlet_id' => Outlet::default()->id]);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.table', null)->where('cart.type', 'pickup'));
});

it('shows each outlet its own kitchen queue on the public board', function () {
    $branch = secondOutlet();
    placeOrder(['outlet' => $branch]);
    placeOrder(['outlet' => $branch]);
    placeOrder();

    $this->get(route('status.index'))->assertInertia(fn (Assert $page) => $page->has('board', 1)->where('kitchen.active_orders', 1));

    $this->put(route('outlets.choose'), ['outlet_id' => $branch->id]);
    $this->get(route('status.index'))->assertInertia(fn (Assert $page) => $page->has('board', 2)->where('kitchen.active_orders', 2));
});

it('messages the customer with the details of the order\'s own outlet', function () {
    $branch = secondOutlet();
    app(Settings::class)->put(['shop.counter' => 'Kaunter Shah Alam'], $branch);
    $order = placeOrder(['outlet' => $branch]);

    app(MarkOrderReady::class)->handle($order);

    expect($order->notificationLogs()->where('template', 'order_ready')->sole()->message)->toContain('Kaunter Shah Alam');
});

it('finds an order from any outlet by its queue token and phone number', function () {
    $branch = secondOutlet();
    $order = placeOrder(['outlet' => $branch]);

    $this->post(route('orders.lookup'), ['order_number' => $order->order_number, 'phone' => '0123456789'])
        ->assertRedirect(route('orders.show', $order));

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and(app(OutletContext::class)->current()->id)->toBe(Outlet::default()->id);
});
