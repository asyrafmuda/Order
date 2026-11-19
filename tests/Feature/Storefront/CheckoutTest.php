<?php

use App\Actions\Payments\StartPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\FakeGateway;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

function checkoutPayload(array $overrides = []): array
{
    return [
        'customer_name' => 'Ahmad Faiz',
        'customer_phone' => '012-345 6789',
        'payment_method' => 'cash',
        ...$overrides,
    ];
}

it('sends an empty ticket back to the menu', function () {
    $this->get(route('checkout.create'))->assertRedirect(route('menu.index'));
});

it('shows the checkout page for a filled ticket', function () {
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->get(route('checkout.create'))
        ->assertInertia(fn (Assert $page) => $page->component('Checkout/Create')->has('paymentMethods', 4)->where('cart.count', 1));
});

it('places a pay-at-counter order and opens the ticket', function () {
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 1500])->id, 'quantity' => 2]);

    $response = $this->post(route('checkout.store'), checkoutPayload(['notes' => 'Extra tissue']));

    $order = Order::query()->sole();

    $response->assertRedirect(route('orders.show', $order));
    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->total)->toBe(3000)
        ->and($order->customer_phone)->toBe('60123456789')
        ->and($order->notes)->toBe('Extra tissue')
        ->and(session('guest_orders'))->toContain($order->uuid)
        ->and(session('cart.lines', []))->toBeEmpty();
});

it('takes online orders through the payment gateway', function () {
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $response = $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'fpx']), ['X-Inertia' => 'true']);

    $order = Order::query()->sole();
    $payment = $order->payments()->sole();

    // Inertia answers with 409 + location so the browser leaves the SPA for the bank page.
    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('/payments/fake/'.$payment->reference)
        ->and($order->status)->toBe(OrderStatus::PendingPayment);

    $this->get($response->headers->get('X-Inertia-Location'))
        ->assertInertia(fn (Assert $page) => $page->component('Payments/Fake')->where('payment.amount', $order->total));

    $callback = $this->post(route('payments.fake.complete', $payment), ['action' => 'approve'])->headers->get('Location');

    $this->get($callback)->assertRedirect(route('orders.show', $order))->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($payment->refresh()->gateway_transaction_id)->not->toBeNull();
});

it('keeps the order open when the payment is declined, and lets the customer retry', function () {
    $order = placeOrder(['method' => PaymentMethod::Card, 'paid' => false]);
    app(StartPayment::class)->handle($order);
    $payment = $order->payments()->sole();

    $this->get(app(FakeGateway::class)->callbackUrl($payment, approved: false))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Failed);

    $this->post(route('orders.pay', $order), [], ['X-Inertia' => 'true'])->assertStatus(409);
    expect($order->payments()->count())->toBe(2);
});

it('rejects a payment callback with a forged signature', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);
    app(StartPayment::class)->handle($order);
    $payment = $order->payments()->sole();

    $forged = str_replace('status=failed', 'status=success', app(FakeGateway::class)->callbackUrl($payment, approved: false));

    $this->get($forged)->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('confirming the same payment twice does not confirm twice', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);
    $url = app(FakeGateway::class)->callbackUrl($order->payments()->sole(), approved: true);

    $this->get($url)->assertRedirect();

    expect($order->histories()->where('status', OrderStatus::Confirmed)->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1);
});

it('validates the delivery address and refuses cash for delivery', function () {
    DeliveryZone::factory()->create(['min_order' => 0]);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
    $this->put(route('cart.preferences'), ['type' => 'delivery']);

    $this->post(route('checkout.store'), checkoutPayload())
        ->assertSessionHasErrors(['line1', 'city', 'postcode', 'payment_method']);

    expect(Order::query()->count())->toBe(0);
});

it('places a delivery order with the address and saves it to the account', function () {
    $zone = DeliveryZone::factory()->create(['fee' => 800, 'min_order' => 0]);
    $customer = userWithRole(UserRole::Customer);
    $this->actingAs($customer);
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 2000])->id]);
    $this->put(route('cart.preferences'), ['type' => 'delivery', 'zone_id' => $zone->id]);

    $this->post(route('checkout.store'), checkoutPayload([
        'payment_method' => 'card',
        'line1' => '12 Jalan Bukit',
        'city' => 'Shah Alam',
        'postcode' => '40000',
        'save_address' => true,
    ]), ['X-Inertia' => 'true'])->assertStatus(409);

    $order = Order::query()->with('delivery')->sole();

    expect($order->total)->toBe(2800)
        ->and($order->user_id)->toBe($customer->id)
        ->and($order->delivery->full_address)->toBe('12 Jalan Bukit, 40000 Shah Alam')
        ->and($customer->addresses()->count())->toBe(1);
});

it('refuses delivery below the zone minimum', function () {
    DeliveryZone::factory()->create(['min_order' => 5000]);
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 1000])->id]);
    $this->put(route('cart.preferences'), ['type' => 'delivery']);

    $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'fpx', 'line1' => '1 Jalan A', 'city' => 'KL', 'postcode' => '50000']))
        ->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0);
});

it('refuses orders while the shop is closed', function () {
    app(Settings::class)->put(['ordering.enabled' => false]);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->post(route('checkout.store'), checkoutPayload())->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0);
});

it('numbers orders in sequence per day', function () {
    expect(placeOrder()->order_number)->toBe('A001')
        ->and(placeOrder()->order_number)->toBe('A002');

    $this->travel(1)->days();

    expect(placeOrder()->order_number)->toBe('A001');
});
