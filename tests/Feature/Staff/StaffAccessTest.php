<?php

use App\Enums\UserRole;

dataset('staff pages', [
    'kitchen display' => 'kitchen.index',
    'counter' => 'counter.index',
    'rider run sheet' => 'rider.index',
]);

it('sends guests to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('staff pages');

it('keeps customers out of every staff workspace', function (string $route) {
    $this->actingAs(userWithRole(UserRole::Customer))
        ->get(route($route))
        ->assertForbidden();
})->with('staff pages');

it('keeps riders out of the kitchen and the counter', function () {
    $rider = userWithRole(UserRole::Rider);

    $this->actingAs($rider)->get(route('kitchen.index'))->assertForbidden();
    $this->actingAs($rider)->get(route('counter.index'))->assertForbidden();
    $this->actingAs($rider)->get(route('rider.index'))->assertOk();
});

it('keeps the kitchen crew out of the counter and the rider run sheet', function () {
    $kitchen = userWithRole(UserRole::Kitchen);

    $this->actingAs($kitchen)->get(route('kitchen.index'))->assertOk();
    $this->actingAs($kitchen)->get(route('counter.index'))->assertForbidden();
    $this->actingAs($kitchen)->get(route('rider.index'))->assertForbidden();
});

it('lets cashiers watch the kitchen as well as work the counter', function () {
    $cashier = userWithRole(UserRole::Cashier);

    $this->actingAs($cashier)->get(route('kitchen.index'))->assertOk();
    $this->actingAs($cashier)->get(route('counter.index'))->assertOk();
});

it('refuses deactivated staff accounts', function () {
    $kitchen = userWithRole(UserRole::Kitchen);
    $kitchen->update(['is_active' => false]);

    $this->actingAs($kitchen)->get(route('kitchen.index'))->assertForbidden();
});

it('refuses staff actions from the wrong role', function () {
    $order = placeOrder();

    $this->actingAs(userWithRole(UserRole::Rider))
        ->post(route('kitchen.orders.ready', $order))
        ->assertForbidden();

    $this->actingAs(userWithRole(UserRole::Kitchen))
        ->post(route('counter.orders.complete', $order))
        ->assertForbidden();
});
