<?php

use App\Actions\Orders\PlaceOrder;
use App\Data\CheckoutData;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\Cart\CartPricer;
use App\Support\OutletContext;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

/** A counter sale paid in cash, as the POS places it. */
function counterSale(int $tendered = 5000): Order
{
    $cashier = userWithRole(UserRole::Cashier);
    $product = menuItem(['price' => 1590, 'code' => 'B1', 'name' => 'Beef Burger']);
    $lines = [['id' => 'l1', 'product_id' => $product->id, 'variant_id' => null, 'modifier_ids' => [], 'combo' => false, 'combo_choice' => null, 'quantity' => 2, 'notes' => 'No onion']];

    return app(PlaceOrder::class)->handle(
        app(CartPricer::class)->price($lines, OrderType::Pickup, null),
        new CheckoutData('Ali', null, null, PaymentMethod::Cash, source: OrderSource::Pos, placedBy: $cashier, paidAtCounter: true, cashTendered: $tendered),
    );
}

it('gives the till everything a receipt shows', function () {
    app(Settings::class)->put(['shop.name' => 'Kedai Ali', 'receipt.paper' => '58', 'receipt.header' => 'SSM 123', 'receipt.footer' => 'Jumpa lagi', 'receipt.kitchen_slip' => true]);
    $order = counterSale(5000);

    $this->actingAs(userWithRole(UserRole::Cashier))->getJson(route('receipts.show', $order))
        ->assertOk()
        ->assertJsonPath('paper', '58')
        ->assertJsonPath('kitchen_slip', true)
        ->assertJsonPath('shop.name', 'Kedai Ali')
        ->assertJsonPath('shop.header', 'SSM 123')
        ->assertJsonPath('shop.footer', 'Jumpa lagi')
        ->assertJsonPath('shop.outlet', null)
        ->assertJsonPath('order.number', $order->order_number)
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.code', 'B1')
        ->assertJsonPath('items.0.notes', 'No onion')
        ->assertJsonPath('items.0.line_total', 3180)
        ->assertJsonPath('totals.total', 3180)
        ->assertJsonPath('payment.paid', true)
        ->assertJsonPath('payment.method_label', PaymentMethod::Cash->label())
        ->assertJsonPath('payment.tendered', 5000)
        ->assertJsonPath('payment.change', 1820)
        // A counter sale carries its number, not a pickup code.
        ->assertJsonPath('order.pickup_code', null);
});

it('keeps the cash handed over at the POS, so a reprint shows the same change', function () {
    $product = menuItem(['price' => 1000]);

    $this->actingAs(userWithRole(UserRole::Cashier))->post(route('pos.orders.store'), [
        'type' => 'pickup',
        'paid' => true,
        'payment_method' => 'cash',
        'tendered' => 2000,
        'lines' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors()->assertSessionHas('receipt.change', 1000);

    $order = Order::query()->sole();

    $this->getJson(route('receipts.show', $order))->assertJsonPath('payment.tendered', 2000)->assertJsonPath('payment.change', 1000);
});

it('says so when the order is not paid yet, and shows no cash line for card payments', function () {
    $unpaid = placeOrder();

    $this->actingAs(userWithRole(UserRole::Cashier))->getJson(route('receipts.show', $unpaid))
        ->assertJsonPath('payment.paid', false)
        ->assertJsonPath('payment.tendered', null)
        ->assertJsonPath('payment.change', null)
        ->assertJsonPath('order.pickup_code', $unpaid->pickup_code);

    $online = placeOrder(['method' => PaymentMethod::Fpx]);
    $this->getJson(route('receipts.show', $online))
        ->assertJsonPath('payment.paid', true)
        ->assertJsonPath('payment.tendered', null)
        ->assertJsonPath('payment.transaction', 'TEST-TXN');
});

it('is for counter staff only', function () {
    $order = placeOrder();

    $this->getJson(route('receipts.show', $order))->assertUnauthorized();
    $this->actingAs(userWithRole(UserRole::Customer))->getJson(route('receipts.show', $order))->assertForbidden();
    $this->actingAs(userWithRole(UserRole::Kitchen))->getJson(route('receipts.show', $order))->assertForbidden();
    $this->actingAs(userWithRole(UserRole::Waiter))->getJson(route('receipts.show', $order))->assertOk();
});

it('does not hand out another outlet\'s receipt, and names the outlet when there are several', function () {
    app(Settings::class)->put(['outlets.multi_enabled' => true]);
    $other = Outlet::factory()->create(['name' => 'Cawangan Dua']);
    $order = placeOrder(['outlet' => $other]);

    $cashier = userWithRole(UserRole::Cashier);
    $cashier->update(['outlet_id' => Outlet::default()->id]);
    $this->actingAs($cashier)->getJson(route('receipts.show', $order))->assertNotFound();

    $cashier->update(['outlet_id' => $other->id]);
    app()->forgetScopedInstances();
    app()->forgetInstance(OutletContext::class);
    $this->actingAs($cashier->refresh())->getJson(route('receipts.show', $order))->assertOk()->assertJsonPath('shop.outlet', 'Cawangan Dua');
});

it('saves the receipt settings and tells the POS to print by itself', function () {
    $this->actingAs(userWithRole(UserRole::Admin));

    $this->put(route('admin.settings.update'), settingsPayload([
        'receipt' => ['paper' => '58', 'header' => 'SST W10-1234', 'footer' => '', 'auto_print' => true, 'kitchen_slip' => true],
    ]))->assertSessionHas('success');

    expect(app(Settings::class)->get('receipt.paper'))->toBe('58')
        ->and(app(Settings::class)->get('receipt.auto_print'))->toBeTrue();

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->where('autoPrint', true));

    $this->put(route('admin.settings.update'), settingsPayload(['receipt' => ['paper' => 'A4', 'auto_print' => false, 'kitchen_slip' => false]]))
        ->assertSessionHasErrors('receipt.paper');
});
