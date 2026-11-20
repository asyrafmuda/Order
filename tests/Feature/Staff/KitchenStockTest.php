<?php

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Support\OutletStock;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the kitchen mark an item sold out and bring it back', function () {
    $product = menuItem();
    $this->actingAs(userWithRole(UserRole::Kitchen));

    $this->patch(route('kitchen.products.status', $product), ['status' => 'sold_out'])->assertSessionHas('success');
    expect(app(OutletStock::class)->isSoldOut($product))->toBeTrue()
        // The business-wide switch is the admin's; the kitchen never touches it.
        ->and($product->refresh()->status)->toBe(ProductStatus::Available);

    $this->patch(route('kitchen.products.status', $product), ['status' => 'available'])->assertSessionHas('success');
    expect(app(OutletStock::class)->isSoldOut($product))->toBeFalse();
});

it('takes a sold out item off the customer\'s ticket straight away', function () {
    $product = menuItem();
    $this->post(route('cart.store'), ['product_id' => $product->id]);

    $this->actingAs(userWithRole(UserRole::Kitchen))->patch(route('kitchen.products.status', $product), ['status' => 'sold_out']);
    auth()->logout();

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.count', 0)
        ->where('categories.0.products.0.is_orderable', false)
        ->where('categories.0.products.0.status_label', 'Sold Out'));

    $this->post(route('cart.store'), ['product_id' => $product->id])->assertSessionHas('error');
});

it('does not let the kitchen hide items or revive admin-managed ones', function () {
    $this->actingAs(userWithRole(UserRole::Kitchen));

    $this->patch(route('kitchen.products.status', menuItem()), ['status' => 'hidden'])->assertSessionHasErrors('status');

    foreach ([ProductStatus::ComingSoon, ProductStatus::SoldOut] as $status) {
        $managed = menuItem(['status' => $status]);
        $this->patch(route('kitchen.products.status', $managed), ['status' => 'available'])->assertStatus(422);

        expect($managed->refresh()->status)->toBe($status);
    }
});

it('is not open to customers or riders', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->patch(route('kitchen.products.status', menuItem()), ['status' => 'sold_out'])
        ->assertForbidden();
})->with([UserRole::Customer, UserRole::Rider]);

it('lists the menu on the kitchen display for the stock panel', function () {
    $product = menuItem(['name' => 'Smash Burger']);
    menuItem(['name' => 'Coming Soon Burger', 'status' => ProductStatus::ComingSoon]);
    app(OutletStock::class)->mark($product, true);

    $this->actingAs(userWithRole(UserRole::Kitchen))->get(route('kitchen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('menu', 2)
            ->where('menu', fn ($menu) => collect($menu)->flatMap(fn ($category) => $category['products'])->pluck('status', 'name')->all() === [
                'Smash Burger' => 'sold_out',
                'Coming Soon Burger' => 'locked',
            ] || collect($menu)->flatMap(fn ($category) => $category['products'])->pluck('status', 'name')->sortKeys()->all() === [
                'Coming Soon Burger' => 'locked',
                'Smash Burger' => 'sold_out',
            ]));
});
