<?php

use App\Contracts\PosProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Enums\UserRole;
use App\Exceptions\PosException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = userWithRole(UserRole::Admin);
    $this->actingAs($this->admin);
});

it('filters the order list', function () {
    $first = placeOrder();
    $second = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

    $this->get(route('admin.orders.index', ['status' => OrderStatus::PendingPayment->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.uuid', $second->uuid)
            ->where('filters.status', 'pending_payment'));

    $this->get(route('admin.orders.index', ['search' => $first->order_number]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.uuid', $first->uuid));

    $this->get(route('admin.orders.index', ['date' => now()->subDay()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

it('shows an order with its history, POS log and messages', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.uuid', $order->uuid)
            ->where('order.customer_phone', $order->customer_phone)
            ->has('order.items', 1)
            ->has('order.histories', 2)
            ->has('posLogs', 1)
            ->has('notifications', 1));
});

it('cancels an order with a reason and refunds it', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    $this->post(route('admin.orders.cancel', $order), ['reason' => 'Grill broke down'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('Grill broke down')
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->histories->last()->user_id)->toBe($this->admin->id);
});

it('needs a reason to cancel', function () {
    $order = placeOrder();

    $this->post(route('admin.orders.cancel', $order), ['reason' => ''])->assertSessionHasErrors('reason');

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('reports an invalid cancel as an error flash', function () {
    $order = placeOrder();
    $order->update(['status' => OrderStatus::Completed]);

    $this->post(route('admin.orders.cancel', $order), ['reason' => 'Too late'])->assertSessionHas('error');
});

it('re-sends a failed order to the POS', function () {
    $order = placeOrder();
    $order->update(['pos_sync_status' => SyncStatus::Failed, 'pos_receipt_number' => null, 'pos_synced_at' => null]);

    $this->post(route('admin.orders.resync', $order))->assertSessionHas('success');

    expect($order->refresh()->pos_sync_status)->toBe(SyncStatus::Synced)
        ->and($order->pos_receipt_number)->not->toBeNull();
});

it('flashes the POS error when a resync fails', function () {
    $order = placeOrder();
    $order->update(['pos_sync_status' => SyncStatus::Failed]);

    $pos = Mockery::mock(PosProvider::class);
    $pos->shouldReceive('name')->andReturn('loyverse');
    $pos->shouldReceive('isConfigured')->andReturn(true);
    $pos->shouldReceive('pushOrder')->andThrow(new PosException('Loyverse is down'));
    $this->app->instance(PosProvider::class, $pos);

    $this->post(route('admin.orders.resync', $order))
        ->assertSessionHas('error', 'POS sync failed: Loyverse is down');

    expect($order->refresh()->pos_sync_status)->toBe(SyncStatus::Failed);
});

it('does not send unpaid online orders to the POS', function () {
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

    $this->post(route('admin.orders.resync', $order))->assertSessionHas('error');

    expect($order->refresh()->pos_sync_status)->toBe(SyncStatus::Pending);
});
