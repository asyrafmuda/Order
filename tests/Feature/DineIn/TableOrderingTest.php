<?php

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\OrderException;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['name' => 'Table 5']);
});

it('seats the customer at the table when its QR code is scanned', function () {
    $this->get(route('tables.show', $this->table->token))
        ->assertRedirect(route('menu.index'))
        ->assertSessionHas('success');

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.type', 'dine_in')
        ->where('cart.table.name', 'Table 5'));
});

it('ignores unknown, inactive and switched-off table codes', function (Closure $prepare) {
    $token = $prepare($this->table);

    $this->get(route('tables.show', $token))->assertRedirect(route('menu.index'))->assertSessionHas('error');
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.table', null)->where('cart.type', 'pickup'));
})->with([
    'unknown code' => [fn () => 'not-a-real-code'],
    'inactive table' => [fn (DiningTable $table) => tap($table)->update(['is_active' => false])->token],
    'dine-in switched off' => [function (DiningTable $table) {
        app(Settings::class)->put(['ordering.dine_in_enabled' => false]);

        return $table->token;
    }],
]);

it('lets a seated guest order without a name, number or account, and pay at the counter', function () {
    app(Settings::class)->put(['payments.cash_requires_account' => true, 'payments.cash_max_amount' => 1000]);
    $this->get(route('tables.show', $this->table->token));
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 2500])->id]);

    $this->get(route('checkout.create'))->assertInertia(fn (Assert $page) => $page
        ->where('paymentMethods.3.value', 'cash')
        ->where('paymentMethods.3.available', true));

    $response = $this->post(route('checkout.store'), ['payment_method' => 'cash']);

    $order = Order::query()->sole();
    $response->assertRedirect(route('orders.show', $order));

    expect($order->type)->toBe(OrderType::DineIn)
        ->and($order->table_label)->toBe('Table 5')
        ->and($order->dining_table_id)->toBe($this->table->id)
        ->and($order->customer_name)->toBe('Table 5')
        ->and($order->customer_phone)->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->delivery_fee)->toBe(0);
});

it('can require table orders to be paid online', function () {
    app(Settings::class)->put(['payments.dine_in_pay_at_counter' => false]);
    $this->get(route('tables.show', $this->table->token));
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->post(route('checkout.store'), ['payment_method' => 'cash'])->assertSessionHasErrors('payment_method');
    $this->post(route('checkout.store'), ['payment_method' => 'fpx'], ['X-Inertia' => 'true'])->assertStatus(409);

    expect(Order::query()->sole()->payment_method)->toBe(PaymentMethod::Fpx);
});

it('shows the table on the customer ticket, the kitchen display and the counter', function () {
    $this->get(route('tables.show', $this->table->token));
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $this->post(route('checkout.store'), ['payment_method' => 'cash', 'customer_name' => 'Aina']);
    $order = Order::query()->sole();

    $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
        ->where('order.type', 'dine_in')
        ->where('order.table_label', 'Table 5')
        ->where('order.customer_name', 'Aina')
        ->where('order.timeline.4.label', 'Ready to Serve'));

    $this->actingAs(userWithRole(UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.0.table_label', 'Table 5'));

    $this->actingAs(userWithRole(UserRole::Cashier))->get(route('counter.index'))
        ->assertInertia(fn (Assert $page) => $page->where('kitchenOrders.0.table_label', 'Table 5'));
});

it('goes back to pickup when the customer leaves the table', function () {
    $this->get(route('tables.show', $this->table->token));

    $this->delete(route('tables.leave'));

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.table', null)->where('cart.type', 'pickup'));
});

it('does not let a customer pick dine-in without scanning a table', function () {
    $this->put(route('cart.preferences'), ['type' => 'dine_in'])->assertSessionHasErrors('type');
});

it('lets a waiter key in an order for a table, to be paid later', function () {
    $this->actingAs($waiter = userWithRole(UserRole::Waiter));

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->has('tables', 1)->where('tables.0.name', 'Table 5'));

    $this->post(route('pos.orders.store'), [
        'type' => 'dine_in',
        'dining_table_id' => $this->table->id,
        'payment_method' => 'cash',
        'paid' => false,
        'lines' => [['product_id' => menuItem()->id, 'quantity' => 2]],
    ])->assertSessionHas('receipt');

    $order = Order::query()->sole();

    expect($order->table_label)->toBe('Table 5')
        ->and($order->customer_name)->toBe('Table 5')
        ->and($order->placed_by_id)->toBe($waiter->id)
        ->and($order->isPaid())->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::Confirmed);
});

it('gives waiters the POS, counter and kitchen screens but not the admin area', function () {
    $this->actingAs(userWithRole(UserRole::Waiter));

    $this->get(route('pos.index'))->assertOk();
    $this->get(route('counter.index'))->assertOk();
    $this->get(route('kitchen.index'))->assertOk();
    $this->get(route('admin.dashboard'))->assertForbidden();
});

describe('switching ways of ordering on and off', function () {
    it('removes delivery everywhere when the shop does not deliver', function () {
        DeliveryZone::factory()->create(['min_order' => 0]);
        $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
        $this->put(route('cart.preferences'), ['type' => 'delivery']);

        app(Settings::class)->put(['ordering.delivery_enabled' => false]);
        app()->forgetScopedInstances();

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('shop.delivery_enabled', false)
            ->where('cart.type', 'pickup')
            ->where('cart.delivery_fee', 0));
    });

    it('refuses an order for a channel that was switched off', function () {
        $order = fn () => placeOrder();

        app(Settings::class)->put(['ordering.pickup_enabled' => false]);

        expect($order)->toThrow(OrderException::class, 'Self Pickup orders are not available right now.');
    });
});

describe('managing tables', function () {
    beforeEach(fn () => $this->actingAs(userWithRole(UserRole::Admin)));

    it('lists tables with their QR links and a print sheet', function () {
        DiningTable::factory()->create(['is_active' => false]);

        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Tables/Index')
            ->has('tables', 2)
            ->where('tables.0.url', route('tables.show', $this->table->token)));

        $this->get(route('admin.tables.print'))->assertInertia(fn (Assert $page) => $page->component('Admin/Tables/Print')->has('tables', 1));
    });

    it('adds a table with its own code, and edits it', function () {
        $this->post(route('admin.tables.store'), ['name' => 'Patio 1', 'seats' => 6, 'sort_order' => 9, 'is_active' => true])->assertSessionHas('success');

        $patio = DiningTable::query()->where('name', 'Patio 1')->sole();
        expect($patio->token)->toHaveLength(16)->not->toBe($this->table->token);

        $this->put(route('admin.tables.update', $patio), ['name' => 'Patio A', 'seats' => null, 'sort_order' => 9, 'is_active' => false])->assertSessionHas('success');
        expect($patio->refresh()->name)->toBe('Patio A')->and($patio->is_active)->toBeFalse();
    });

    it('issues a new code that makes the old one stop working', function () {
        $old = $this->table->token;

        $this->post(route('admin.tables.regenerate', $this->table))->assertSessionHas('success');

        expect($this->table->refresh()->token)->not->toBe($old);

        auth()->logout();
        $this->get(route('tables.show', $old))->assertSessionHas('error');
    });

    it('keeps the table name on past orders when the table is removed', function () {
        auth()->logout();
        $this->get(route('tables.show', $this->table->token));
        $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
        $this->post(route('checkout.store'), ['payment_method' => 'cash']);
        $order = Order::query()->sole();
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->delete(route('admin.tables.destroy', $this->table))->assertSessionHas('error');

        app(CancelOrder::class)->handle($order, 'test');
        $this->delete(route('admin.tables.destroy', $this->table))->assertSessionHas('success');

        expect($order->refresh()->table_label)->toBe('Table 5')->and($order->dining_table_id)->toBeNull();
    });

    it('is for admins only', function () {
        $this->actingAs(userWithRole(UserRole::Cashier))->get(route('admin.tables.index'))->assertForbidden();
    });
});
