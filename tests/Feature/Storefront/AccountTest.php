<?php

use App\Enums\UserRole;
use App\Models\Address;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->customer = userWithRole(UserRole::Customer);
});

it('requires signing in', function () {
    $this->get(route('account.orders'))->assertRedirect(route('login'));
});

it('lists only the customer\'s own orders', function () {
    placeOrder(['user' => $this->customer]);
    placeOrder(['user' => userWithRole(UserRole::Customer)]);

    $this->actingAs($this->customer)->get(route('account.orders'))
        ->assertInertia(fn (Assert $page) => $page->component('Account/Orders')->has('orders.data', 1));
});

it('updates the profile and normalises the phone number', function () {
    $this->actingAs($this->customer)
        ->put(route('account.profile.update'), ['name' => 'New Name', 'email' => 'new@example.com', 'phone' => '011-2222 3333'])
        ->assertSessionHas('success');

    expect($this->customer->refresh()->name)->toBe('New Name')
        ->and($this->customer->phone)->toBe('601122223333');
});

it('changes the password only with the current one', function () {
    $payload = ['password' => 'new-burger-sauce-42', 'password_confirmation' => 'new-burger-sauce-42'];

    $this->actingAs($this->customer)
        ->put(route('account.password.update'), ['current_password' => 'wrong', ...$payload])
        ->assertSessionHasErrors('current_password');

    $this->put(route('account.password.update'), ['current_password' => 'password', ...$payload])->assertSessionHas('success');
});

it('manages saved addresses and keeps a single default', function () {
    $address = ['label' => 'Home', 'recipient_name' => 'Ahmad', 'phone' => '0123456789', 'line1' => '1 Jalan A', 'city' => 'KL', 'postcode' => '50000', 'is_default' => true];

    $this->actingAs($this->customer)->post(route('account.addresses.store'), $address)->assertSessionHas('success');
    $this->post(route('account.addresses.store'), [...$address, 'label' => 'Office', 'line1' => '2 Jalan B']);

    expect($this->customer->addresses()->where('is_default', true)->pluck('label')->all())->toBe(['Office']);

    $first = $this->customer->addresses()->where('label', 'Home')->sole();
    $this->delete(route('account.addresses.destroy', $first))->assertSessionHas('success');

    expect($this->customer->addresses()->count())->toBe(1);
});

it('does not let a customer touch someone else\'s address', function () {
    $other = userWithRole(UserRole::Customer);
    $address = $other->addresses()->create(['label' => 'Home', 'recipient_name' => 'X', 'phone' => '60123456789', 'line1' => '1 Jalan A', 'city' => 'KL', 'postcode' => '50000']);

    $this->actingAs($this->customer)
        ->put(route('account.addresses.update', $address), ['label' => 'Mine', 'recipient_name' => 'X', 'phone' => '0123456789', 'line1' => 'Hacked', 'city' => 'KL', 'postcode' => '50000'])
        ->assertForbidden();

    $this->delete(route('account.addresses.destroy', $address))->assertForbidden();

    expect(Address::query()->sole()->line1)->toBe('1 Jalan A');
});
