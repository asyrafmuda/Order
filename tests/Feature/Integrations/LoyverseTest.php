<?php

use App\Actions\Pos\PushOrderToPos;
use App\Actions\Pos\SyncPosMenu;
use App\Enums\PaymentMethod;
use App\Enums\SyncStatus;
use App\Exceptions\PosException;
use App\Jobs\SyncPosMenuJob;
use App\Models\Product;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('pos.default', 'loyverse');
    config()->set('pos.loyverse.access_token', 'test-token');
    config()->set('pos.loyverse.store_id', 'store-1');
    config()->set('pos.loyverse.payment_type_id', 'pay-1');
    config()->set('pos.loyverse.webhook_secret', 'hook-secret');
    Http::preventStrayRequests();
});

it('links menu items to Loyverse variants by SKU, then by name', function () {
    $bySku = menuItem(['code' => 'B4', 'name' => 'Super Deluxe Burger']);
    $byName = menuItem(['code' => null, 'name' => 'Cheezy Wedges']);
    $unknown = menuItem(['code' => 'ZZ', 'name' => 'Not In Loyverse']);

    Http::fake(['api.loyverse.com/v1.0/items*' => Http::response(['items' => [
        ['id' => 'item-1', 'item_name' => 'Deluxe (POS name)', 'variants' => [['variant_id' => 'var-1', 'sku' => 'B4', 'default_price' => 20.9]]],
        ['id' => 'item-2', 'item_name' => 'cheezy wedges', 'variants' => [['variant_id' => 'var-2', 'sku' => '10002', 'default_price' => 6.9]]],
    ]])]);

    $result = app(SyncPosMenu::class)->handle();

    expect($result['linked'])->toBe(2)
        ->and($result['unmatched'])->toBe([$unknown->name])
        ->and($bySku->refresh()->pos_variant_id)->toBe('var-1')
        ->and($byName->refresh()->pos_variant_id)->toBe('var-2');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('creates a Loyverse receipt for a confirmed order', function () {
    Queue::fake();
    $product = menuItem(['price' => 1500]);
    $product->update(['pos_variant_id' => 'var-9']);
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'products' => [$product], 'quantity' => 2]);

    Http::fake(['api.loyverse.com/v1.0/receipts' => Http::response(['receipt_number' => '2-1007'])]);

    app(PushOrderToPos::class)->handle($order);

    expect($order->refresh()->pos_sync_status)->toBe(SyncStatus::Synced)
        ->and($order->pos_receipt_number)->toBe('2-1007');

    Http::assertSent(function (Request $request) {
        return $request['store_id'] === 'store-1'
            && $request['order'] === 'A001'
            && $request['line_items'][0] == ['variant_id' => 'var-9', 'quantity' => 2, 'price' => 15.0]
            && $request['payments'][0] == ['payment_type_id' => 'pay-1', 'money_amount' => 30.0];
    });
});

it('records a failed sync and leaves the order with the kitchen', function () {
    Queue::fake();
    $order = placeOrder(['method' => PaymentMethod::Fpx]);

    // The product was never linked to a Loyverse item.
    expect(fn () => app(PushOrderToPos::class)->handle($order))->toThrow(PosException::class);

    expect($order->refresh()->pos_sync_status)->toBe(SyncStatus::Failed)
        ->and($order->status->value)->toBe('confirmed')
        ->and($order->posSyncLogs()->sole()->error)->toContain('not linked');
});

it('surfaces Loyverse API errors', function () {
    Queue::fake();
    $product = menuItem();
    $product->update(['pos_variant_id' => 'var-9']);
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'products' => [$product]]);

    Http::fake(['*' => Http::response(['errors' => [['code' => 'UNAUTHORIZED', 'details' => 'Invalid token']]], 401)]);

    expect(fn () => app(PushOrderToPos::class)->handle($order))->toThrow(PosException::class, 'HTTP 401: Invalid token');
});

it('accepts only signed webhooks and re-syncs the menu on catalogue changes', function () {
    Queue::fake();
    $body = json_encode(['type' => 'items.update', 'merchant_id' => 'm-1']);
    $signature = base64_encode(hash_hmac('sha256', $body, 'hook-secret', true));
    $server = fn (string $sig) => ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LOYVERSE_SIGNATURE' => $sig];

    $this->call('POST', route('webhooks.loyverse'), server: $server('forged'), content: $body)->assertForbidden();
    $this->call('POST', route('webhooks.loyverse'), server: $server($signature), content: $body)->assertOk();

    Queue::assertPushed(SyncPosMenuJob::class);
    expect(Product::query()->count())->toBe(0);
});

it('never lets a POS outage break the customer\'s request', function () {
    Http::fake(['*' => Http::response(status: 503)]);
    $product = menuItem();
    $product->update(['pos_variant_id' => 'var-9']);

    // The test queue is "sync", so the POS push happens inside this call.
    $order = placeOrder(['method' => PaymentMethod::Fpx, 'products' => [$product]]);

    expect($order->status->value)->toBe('confirmed')
        ->and($order->pos_sync_status)->toBe(SyncStatus::Failed);
});
