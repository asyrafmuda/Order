<?php

use App\Enums\OrderType;
use App\Enums\ProductStatus;
use App\Models\ComboOffer;
use App\Models\DeliveryZone;
use App\Models\ModifierGroup;
use App\Services\Cart\Cart;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the menu with visible products only', function () {
    $burger = menuItem(['name' => 'Smash Burger']);
    menuItem(['name' => 'Secret Burger', 'status' => ProductStatus::Hidden]);

    $this->get(route('menu.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Menu/Index')
            ->has('categories', 1)
            ->where('categories.0.products.0.name', $burger->name)
            ->where('cart.count', 0));
});

it('adds an item and prices the ticket on the server', function () {
    $product = menuItem(['price' => 1490]);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();

    $cart = app(Cart::class)->priced();

    expect($cart->count())->toBe(2)
        ->and($cart->subtotal())->toBe(2980)
        ->and($cart->taxAmount())->toBe(169) // 6% SST included in RM 29.80
        ->and($cart->total())->toBe(2980);
});

it('merges identical lines and keeps different options apart', function () {
    $product = menuItem();
    $product->variants()->createMany([
        ['name' => 'Single', 'price' => 1390, 'is_default' => true],
        ['name' => 'Double', 'price' => 1990],
    ]);
    [$single, $double] = $product->variants;

    $this->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $single->id]);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $single->id]);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $double->id]);

    $cart = app(Cart::class)->priced();

    expect($cart->lines)->toHaveCount(2)
        ->and($cart->subtotal())->toBe(1390 * 2 + 1990);
});

it('charges for add-ons and the combo upgrade', function () {
    ComboOffer::query()->create(['name' => 'Combo Set', 'price' => 790, 'choices' => ['Iced Soft Drink', 'Iced Lemon Tea'], 'is_active' => true]);
    $product = menuItem(['price' => 2090, 'is_combo_eligible' => true]);
    $group = ModifierGroup::query()->create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 2]);
    $sauce = $group->modifiers()->create(['name' => 'Extra Boss Sauce', 'price' => 100]);
    $product->modifierGroups()->attach($group);

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'modifier_ids' => [$sauce->id],
        'combo' => true,
        'combo_choice' => 'Iced Lemon Tea',
        'notes' => 'No pickles',
    ])->assertSessionHas('success');

    $line = app(Cart::class)->priced()->lines->first();

    expect($line->unitPrice())->toBe(2090 + 100 + 790)
        ->and($line->comboChoice)->toBe('Iced Lemon Tea')
        ->and($line->notes)->toBe('No pickles');
});

it('refuses items that cannot be sold as requested', function (Closure $payload) {
    $this->post(route('cart.store'), $payload())->assertSessionHas('error');

    expect(app(Cart::class)->isEmpty())->toBeTrue();
})->with([
    'sold out' => [fn () => ['product_id' => menuItem(['status' => ProductStatus::SoldOut])->id]],
    'unknown product' => [fn () => ['product_id' => 999]],
    'combo on a non-combo item' => [fn () => ['product_id' => menuItem()->id, 'combo' => true]],
    'option from another product' => [function () {
        $group = ModifierGroup::query()->create(['name' => 'Other', 'min_select' => 0, 'max_select' => 1]);

        return ['product_id' => menuItem()->id, 'modifier_ids' => [$group->modifiers()->create(['name' => 'X', 'price' => 0])->id]];
    }],
    'missing required option' => [function () {
        $product = menuItem();
        $group = ModifierGroup::query()->create(['name' => 'Sauce', 'min_select' => 1, 'max_select' => 1]);
        $group->modifiers()->create(['name' => 'BBQ', 'price' => 0]);
        $product->modifierGroups()->attach($group);

        return ['product_id' => $product->id];
    }],
]);

it('updates quantities and removes lines', function () {
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $lineId = app(Cart::class)->lines()[0]['id'];

    $this->patch(route('cart.update', $lineId), ['quantity' => 3]);
    expect(app(Cart::class)->lines()[0]['quantity'])->toBe(3);

    $this->patch(route('cart.update', $lineId), ['quantity' => 0]);
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('drops a line from the ticket when the product sells out', function () {
    $product = menuItem();
    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $product->update(['status' => ProductStatus::SoldOut]);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.count', 0));
});

it('adds the delivery fee of the chosen zone', function () {
    $near = DeliveryZone::factory()->create(['fee' => 500, 'min_order' => 3000, 'sort_order' => 0]);
    $far = DeliveryZone::factory()->create(['fee' => 1200, 'min_order' => 0, 'sort_order' => 1]);
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 2000])->id]);

    $this->put(route('cart.preferences'), ['type' => 'delivery'])->assertRedirect();

    $cart = app(Cart::class)->priced();
    expect($cart->type)->toBe(OrderType::Delivery)
        ->and($cart->zone->id)->toBe($near->id)
        ->and($cart->total())->toBe(2500)
        ->and($cart->minOrderShortfall())->toBe(1000);

    $this->put(route('cart.preferences'), ['zone_id' => $far->id]);
    app()->forgetScopedInstances();

    expect(app(Cart::class)->priced()->total())->toBe(3200);
});
