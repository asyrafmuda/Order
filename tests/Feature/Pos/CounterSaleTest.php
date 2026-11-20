<?php

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cashier = userWithRole(UserRole::Cashier);
    $this->actingAs($this->cashier);
});

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function posPayload(array $lines, array $overrides = []): array
{
    return [
        'type' => 'pickup',
        'payment_method' => 'cash',
        'paid' => true,
        'lines' => $lines,
        ...$overrides,
    ];
}

it('is for cashiers and admins only', function (UserRole $role, int $status) {
    $this->actingAs(userWithRole($role))->get(route('pos.index'))->assertStatus($status);
})->with([
    [UserRole::Admin, 200],
    [UserRole::Cashier, 200],
    [UserRole::Kitchen, 403],
    [UserRole::Rider, 403],
    [UserRole::Customer, 403],
]);

it('shows the menu, quick notes and sold out items on the POS', function () {
    menuItem(['name' => 'Smash Burger']);
    menuItem(['name' => 'Gone Burger', 'status' => ProductStatus::SoldOut]);
    menuItem(['name' => 'Secret', 'status' => ProductStatus::Hidden]);

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Pos/Index')
        ->has('categories', 2)
        ->has('quickNotes')
        ->where('tax.rate', 6)
        ->has('recent', 0));
});

it('rings up a paid walk-in order and sends it straight to the kitchen', function () {
    $burger = menuItem(['price' => 1390]);

    $this->post(route('pos.orders.store'), posPayload(
        [['product_id' => $burger->id, 'quantity' => 2]],
        ['tendered' => 5000],
    ))
        ->assertRedirect(route('pos.index'))
        ->assertSessionHas('receipt', fn (array $receipt) => $receipt['order_number'] === 'A001'
            && $receipt['total'] === 2780
            && $receipt['change'] === 2220
            && $receipt['paid'] === true);

    $order = Order::query()->sole();

    expect($order->source)->toBe(OrderSource::Pos)
        ->and($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->customer_name)->toBe('Walk-in')
        ->and($order->customer_phone)->toBeNull()
        ->and($order->placed_by_id)->toBe($this->cashier->id)
        ->and($order->payments()->sole()->gateway)->toBe('counter');
});

it('keeps every kitchen instruction: size, held items, extras and notes', function () {
    $burger = menuItem(['name' => 'Burger Ayam Special']);
    $burger->variants()->createMany([['name' => 'Single', 'price' => 1390, 'is_default' => true], ['name' => 'Double', 'price' => 1990]]);
    $hold = ModifierGroup::query()->create(['name' => 'Hold the...', 'min_select' => 0, 'max_select' => 5]);
    $extra = ModifierGroup::query()->create(['name' => 'A Little Extra', 'min_select' => 0, 'max_select' => 3]);
    $noVeg = $hold->modifiers()->create(['name' => 'No Veggies', 'price' => 0]);
    $noSauce = $hold->modifiers()->create(['name' => 'No Sauce', 'price' => 0]);
    $mayo = $extra->modifiers()->create(['name' => 'Extra Mayo', 'price' => 0]);
    $burger->modifierGroups()->attach([$hold->id, $extra->id]);

    $this->post(route('pos.orders.store'), posPayload([[
        'product_id' => $burger->id,
        'variant_id' => $burger->variants->firstWhere('name', 'Double')->id,
        'modifier_ids' => [$noVeg->id, $noSauce->id, $mayo->id],
        'quantity' => 1,
        'notes' => 'Potong dua',
    ]], ['type' => 'dine_in', 'customer_name' => 'Meja 5']))->assertSessionHas('receipt');

    $item = Order::query()->sole()->items()->with('modifiers')->sole();

    expect($item->variant_name)->toBe('Double')
        ->and($item->modifiers->pluck('name')->all())->toBe(['No Veggies', 'No Sauce', 'Extra Mayo'])
        ->and($item->notes)->toBe('Potong dua')
        ->and($item->unit_price)->toBe(1990)
        ->and($item->order->type)->toBe(OrderType::DineIn)
        ->and($item->order->customer_name)->toBe('Meja 5');
});

it('prices the ticket on the server, whatever the browser thinks', function () {
    $burger = menuItem(['price' => 1500]);

    $this->post(route('pos.orders.store'), posPayload([
        ['product_id' => $burger->id, 'quantity' => 1, 'unit_price' => 1, 'price' => 1],
    ]));

    expect(Order::query()->sole()->total)->toBe(1500);
});

it('takes card and QR payments at the till', function () {
    $this->post(route('pos.orders.store'), posPayload(
        [['product_id' => menuItem()->id, 'quantity' => 1]],
        ['payment_method' => 'card'],
    ))->assertSessionHas('receipt', fn (array $receipt) => $receipt['change'] === null);

    $order = Order::query()->sole();

    expect($order->payment_method)->toBe(PaymentMethod::Card)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->payments()->sole()->gateway)->toBe('counter');
});

it('lets the customer pay later, at hand-over', function () {
    $this->post(route('pos.orders.store'), posPayload(
        [['product_id' => menuItem()->id, 'quantity' => 1]],
        ['paid' => false, 'payment_method' => 'card'],
    ));

    $order = Order::query()->sole();

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->isPaid())->toBeFalse()
        ->and($order->payment_method)->toBe(PaymentMethod::Cash);

    $this->post(route('counter.orders.pay', $order))->assertSessionHas('success');
    expect($order->refresh()->isPaid())->toBeTrue();
});

it('refuses cash that does not cover the total', function () {
    $this->post(route('pos.orders.store'), posPayload(
        [['product_id' => menuItem(['price' => 2000])->id, 'quantity' => 1]],
        ['tendered' => 1000],
    ))->assertSessionHasErrors('tendered');

    expect(Order::query()->count())->toBe(0);
});

it('refuses the whole ticket when an item has just sold out', function () {
    $available = menuItem();
    $soldOut = menuItem(['status' => ProductStatus::SoldOut]);

    $this->post(route('pos.orders.store'), posPayload([
        ['product_id' => $available->id, 'quantity' => 1],
        ['product_id' => $soldOut->id, 'quantity' => 1],
    ]))->assertSessionHasErrors('lines');

    expect(Order::query()->count())->toBe(0);
});

it('needs at least one item', function () {
    $this->post(route('pos.orders.store'), posPayload([]))->assertSessionHasErrors('lines');
});

it('takes orders outside online ordering hours', function () {
    app(Settings::class)->put(['ordering.enabled' => false]);

    $this->post(route('pos.orders.store'), posPayload([['product_id' => menuItem()->id, 'quantity' => 1]]))
        ->assertSessionHas('receipt');
});

it('shows counter sales on the kitchen display and the counter board', function () {
    $this->post(route('pos.orders.store'), posPayload([['product_id' => menuItem()->id, 'quantity' => 1]], ['type' => 'dine_in']));

    $this->get(route('counter.index'))->assertInertia(fn (Assert $page) => $page->has('kitchenOrders', 1)->where('kitchenOrders.0.source', 'pos'));
    $this->actingAs(userWithRole(UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.type', 'dine_in'));
});

it('opens the ticket of a walk-in without a phone number', function () {
    $this->post(route('pos.orders.store'), posPayload([['product_id' => menuItem()->id, 'quantity' => 1]]));
    $order = Order::query()->sole();

    $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
        ->where('order.customer_phone', null)
        ->where('order.timeline.0.detail', 'Counter')
        ->where('order.timeline.1.label', 'Paid at Counter'));

    $this->get(route('orders.invoice', $order))->assertOk();
});
