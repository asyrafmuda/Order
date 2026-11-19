<?php

use App\Actions\Delivery\AssignRider;
use App\Actions\Kitchen\MarkOrderReady;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->riderUser = userWithRole(UserRole::Rider);
    $this->rider = $this->riderUser->rider;
});

function riderDelivery(User $riderUser, bool $ready = true): Order
{
    $order = placeOrder(['type' => OrderType::Delivery]);

    if ($ready) {
        $order = app(MarkOrderReady::class)->handle($order);
    }

    app(AssignRider::class)->handle($order, $riderUser->rider);

    return $order->refresh();
}

it('shows the rider only their own open deliveries', function () {
    $mine = riderDelivery($this->riderUser);
    riderDelivery(userWithRole(UserRole::Rider));

    $this->actingAs($this->riderUser)
        ->get(route('rider.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Rider/Index')
            ->where('rider.is_available', true)
            ->has('deliveries', 1)
            ->where('deliveries.0.id', $mine->delivery->id)
            ->where('deliveries.0.status', 'assigned')
            ->where('deliveries.0.address', '12 Jalan Bukit, 40000 Shah Alam')
            ->where('deliveries.0.order.order_number', $mine->order_number)
            ->where('deliveries.0.order.status', 'ready')
            ->has('deliveries.0.order.items', 1)
            ->has('completed', 0));
});

it('creates a rider profile for a rider account that has none', function () {
    $user = User::factory()->role(UserRole::Rider)->create();

    $this->actingAs($user)->get(route('rider.index'))->assertOk();

    expect($user->rider()->exists())->toBeTrue();
});

it('takes a delivery from pickup to delivered', function () {
    $order = riderDelivery($this->riderUser);
    $delivery = $order->delivery;

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.pickup', $delivery))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::PickedUp)
        ->and($delivery->picked_up_at)->not->toBeNull();

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.deliver', $delivery))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery->delivered_at)->not->toBeNull();

    $this->actingAs($this->riderUser)
        ->get(route('rider.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('deliveries', 0)
            ->has('completed', 1)
            ->where('completed.0.id', $delivery->id));
});

it('cannot pick up an order the kitchen has not finished', function () {
    $order = riderDelivery($this->riderUser, ready: false);

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.pickup', $order->delivery))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($order->delivery->status)->toBe(DeliveryStatus::Assigned);
});

it('cannot mark a delivery delivered before picking it up', function () {
    $order = riderDelivery($this->riderUser);

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.deliver', $order->delivery))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('cannot act on another rider\'s delivery', function () {
    $order = riderDelivery(userWithRole(UserRole::Rider));

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.pickup', $order->delivery))
        ->assertForbidden();

    $this->actingAs($this->riderUser)
        ->post(route('rider.deliveries.deliver', $order->delivery))
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('toggles availability', function () {
    $this->actingAs($this->riderUser)
        ->put(route('rider.availability'), ['is_available' => false])
        ->assertSessionHas('success');

    expect($this->rider->refresh()->is_available)->toBeFalse();

    $this->actingAs($this->riderUser)->put(route('rider.availability'), ['is_available' => true]);

    expect($this->rider->refresh()->is_available)->toBeTrue();
});
