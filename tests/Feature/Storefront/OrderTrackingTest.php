<?php

use App\Actions\Kitchen\MarkOrderReady;
use App\Actions\Orders\CompleteOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Support\OrderTimeline;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the digital ticket to anyone with the link, masking the phone number', function () {
    $order = placeOrder();

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Show')
            ->where('order.order_number', 'A001')
            ->where('order.customer_phone', '6012••••789')
            ->has('order.items', 1)
            ->has('order.timeline', 6));
});

it('shows contact details to the guest who placed the order', function () {
    $order = placeOrder();

    $this->withSession(['guest_orders' => [$order->uuid]])
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.customer_phone', '60123456789'));
});

it('shows contact details to the owner and to staff', function () {
    $customer = userWithRole(UserRole::Customer);
    $order = placeOrder(['user' => $customer]);

    $this->actingAs($customer)->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.customer_phone', '60123456789'));

    $this->actingAs(userWithRole(UserRole::Cashier))->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.customer_phone', '60123456789'));
});

it('highlights the step the order is currently at', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);
    $active = fn () => collect(OrderTimeline::for($order->refresh()))->firstWhere('state', 'active')['key'];

    expect($active())->toBe('kitchen');

    app(MarkOrderReady::class)->handle($order);
    expect($active())->toBe('ready');

    app(CompleteOrder::class)->handle($order->refresh());
    expect(collect(OrderTimeline::for($order->refresh()))->every(fn ($step) => $step['state'] === 'done'))->toBeTrue();
});

it('finds an order by queue token and phone number', function () {
    $order = placeOrder();

    $this->post(route('orders.lookup'), ['order_number' => 'a001', 'phone' => '0123456789'])
        ->assertRedirect(route('orders.show', $order));

    $this->post(route('orders.lookup'), ['order_number' => 'A001', 'phone' => '0199999999'])
        ->assertSessionHasErrors('order_number');
});

it('lists recent orders from this browser on the tracking page', function () {
    $order = placeOrder();
    placeOrder();

    $this->withSession(['guest_orders' => [$order->uuid]])
        ->get(route('orders.track'))
        ->assertInertia(fn (Assert $page) => $page->component('Orders/Track')->has('recent', 1));
});

it('lets the customer cancel only before paying', function () {
    $unpaid = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

    $this->post(route('orders.cancel', $unpaid))->assertForbidden();

    $this->withSession(['guest_orders' => [$unpaid->uuid]])
        ->post(route('orders.cancel', $unpaid))
        ->assertRedirect(route('orders.show', $unpaid));
    expect($unpaid->refresh()->status)->toBe(OrderStatus::Cancelled);

    $paid = placeOrder(['method' => PaymentMethod::Fpx]);
    $this->withSession(['guest_orders' => [$paid->uuid]])->post(route('orders.cancel', $paid))->assertStatus(409);
});

it('downloads a PDF invoice', function () {
    $order = placeOrder();

    $response = $this->get(route('orders.invoice', $order))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('A001.pdf');
});

it('shows the public kitchen board without customer details', function () {
    placeOrder();

    $this->get(route('status.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Status/Index')
            ->has('board', 1)
            ->where('board.0.order_number', 'A001')
            ->missing('board.0.customer_name')
            ->missing('stations')
            ->missing('sync'));
});

it('cancels unpaid online orders that timed out', function () {
    $stale = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);
    $fresh = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);
    $stale->forceFill(['created_at' => now()->subHour()])->save();

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();

    expect($stale->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($fresh->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('publishes the terms and the privacy notice', function (string $routeName, string $component) {
    $this->get(route($routeName))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['legal.terms', 'Legal/Terms'],
    ['legal.privacy', 'Legal/Privacy'],
]);
