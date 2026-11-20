<?php

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the kitchen mark an item sold out and bring it back', function () {
    $product = menuItem();
    $this->actingAs(userWithRole(UserRole::Kitchen));

    $this->patch(route('kitchen.products.status', $product), ['status' => 'sold_out'])->assertSessionHas('success');
    expect($product->refresh()->status)->toBe(ProductStatus::SoldOut);

    $this->patch(route('kitchen.products.status', $product), ['status' => 'available'])->assertSessionHas('success');
    expect($product->refresh()->status)->toBe(ProductStatus::Available);
});

it('takes a sold out item off the customer\'s ticket straight away', function () {
    $product = menuItem();
    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs(userWithRole(UserRole::Kitchen))->patch(route('kitchen.products.status', $product), ['status' => 'sold_out']);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.count', 0));
});

it('does not let the kitchen hide items or revive admin-managed ones', function () {
    $this->actingAs(userWithRole(UserRole::Kitchen));

    $this->patch(route('kitchen.products.status', menuItem()), ['status' => 'hidden'])->assertSessionHasErrors('status');

    $comingSoon = menuItem(['status' => ProductStatus::ComingSoon]);
    $this->patch(route('kitchen.products.status', $comingSoon), ['status' => 'available'])->assertStatus(422);

    expect($comingSoon->refresh()->status)->toBe(ProductStatus::ComingSoon);
});

it('is not open to customers or riders', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->patch(route('kitchen.products.status', menuItem()), ['status' => 'sold_out'])
        ->assertForbidden();
})->with([UserRole::Customer, UserRole::Rider]);

it('lists the menu on the kitchen display for the stock panel', function () {
    menuItem(['name' => 'Smash Burger']);

    $this->actingAs(userWithRole(UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page->has('menu', 1)->where('menu.0.products.0.name', 'Smash Burger'));
});
