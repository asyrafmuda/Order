<?php

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\PaymentOptions;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

function cashReason(?User $customer = null, int $total = 2000, OrderType $type = OrderType::Pickup): ?string
{
    return app(PaymentOptions::class)->unavailableReason(PaymentMethod::Cash, $type, $customer, $total);
}

it('offers cash to guests only when the shop allows it', function () {
    expect(cashReason())->toBeNull();

    app(Settings::class)->put(['payments.cash_requires_account' => true]);

    expect(cashReason())->toBe('Sign in to pay at the counter')
        ->and(cashReason(userWithRole(UserRole::Customer)))->toBeNull();
});

it('caps the size of a cash order', function () {
    app(Settings::class)->put(['payments.cash_max_amount' => 5000]);

    expect(cashReason(total: 5000))->toBeNull()
        ->and(cashReason(total: 5001))->toBe('Orders above RM 50.00 must be paid online');
});

it('never offers cash for delivery', function () {
    expect(cashReason(type: OrderType::Delivery))->toBe('Pickup orders only');
});

it('hides payment methods the shop switched off', function () {
    app(Settings::class)->put(['payments.cash_enabled' => false, 'payments.ewallet_enabled' => false]);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->get(route('checkout.create'))->assertInertia(fn (Assert $page) => $page
        ->has('paymentMethods', 2)
        ->where('paymentMethods.0.value', 'fpx')
        ->where('paymentMethods.1.value', 'card'));
});

it('tells a guest why cash is unavailable and refuses it at checkout', function () {
    app(Settings::class)->put(['payments.cash_requires_account' => true]);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->get(route('checkout.create'))->assertInertia(fn (Assert $page) => $page
        ->where('paymentMethods.3.value', 'cash')
        ->where('paymentMethods.3.available', false)
        ->where('paymentMethods.3.reason', 'Sign in to pay at the counter'));

    $this->post(route('checkout.store'), ['customer_name' => 'Guest', 'customer_phone' => '0123456789', 'payment_method' => 'cash'])
        ->assertSessionHasErrors('payment_method');

    expect(Order::query()->count())->toBe(0);
});

it('refuses a switched-off method even if the request asks for it', function () {
    app(Settings::class)->put(['payments.fpx_enabled' => false]);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->post(route('checkout.store'), ['customer_name' => 'Guest', 'customer_phone' => '0123456789', 'payment_method' => 'fpx'])
        ->assertSessionHasErrors('payment_method');
});

it('lets a signed-in customer pay at the counter within the limit', function () {
    app(Settings::class)->put(['payments.cash_requires_account' => true, 'payments.cash_max_amount' => 5000]);
    $this->actingAs(userWithRole(UserRole::Customer));
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 2000])->id]);

    $this->post(route('checkout.store'), ['customer_name' => 'Ahmad', 'customer_phone' => '0123456789', 'payment_method' => 'cash'])
        ->assertRedirect();

    expect(Order::query()->sole()->payment_method)->toBe(PaymentMethod::Cash);
});
