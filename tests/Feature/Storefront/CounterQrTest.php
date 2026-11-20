<?php

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->outlet = Outlet::default();

    // The rules for orders placed from home: an account for cash, and a cap on its size.
    app(Settings::class)->put(['payments.cash_requires_account' => true, 'payments.cash_max_amount' => 1000]);
});

/** Scan the outlet's shop QR code, as a customer standing at the counter would. */
function scanShopCode(Outlet $outlet): TestResponse
{
    return test()->get(route('shop.show', $outlet->refresh()->qr_token));
}

it('gives every outlet its own shop code', function () {
    $branch = Outlet::factory()->create();

    expect($this->outlet->qr_token)->toHaveLength(16)
        ->and($branch->qr_token)->toHaveLength(16)->not->toBe($this->outlet->qr_token);
});

it('opens the menu for ordering at the counter', function () {
    scanShopCode($this->outlet)->assertRedirect(route('menu.index'))->assertSessionHas('success');

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.at_counter', true)
        ->where('cart.type', 'pickup')
        ->where('cart.table', null));
});

it('takes a walk-up order with no name, no number and no account, paid at the counter', function () {
    $burger = menuItem(['price' => 1500]);

    scanShopCode($this->outlet);
    $this->post(route('cart.store'), ['product_id' => $burger->id, 'quantity' => 3]);

    $this->get(route('checkout.create'))->assertInertia(fn (Assert $page) => $page
        ->where('cart.at_counter', true)
        ->where('paymentMethods', fn ($methods) => collect($methods)->firstWhere('value', 'cash')['available'] === true));

    // RM 45: above the cash limit for orders placed from home, fine for someone standing here.
    $this->post(route('checkout.store'), ['payment_method' => 'cash'])->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->type)->toBe(OrderType::Pickup)
        ->and($order->customer_name)->toBe('Walk-in')
        ->and($order->customer_phone)->toBeNull()
        ->and($order->user_id)->toBeNull()
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and($order->total)->toBe(4500)
        ->and($order->order_number)->toBe('A001');

    $this->get(route('orders.show', $order))->assertOk();

    // Still at the counter for the next round.
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', true)->where('cart.count', 0));
});

it('keeps the usual rules for someone who did not scan the code', function () {
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 500])->id]);

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false));

    $this->post(route('checkout.store'), ['payment_method' => 'cash'])
        ->assertSessionHasErrors(['customer_name', 'customer_phone', 'payment_method']);

    expect(Order::query()->count())->toBe(0);
});

it('stops treating a scan as "at the counter" after a few hours', function () {
    scanShopCode($this->outlet);
    $this->post(route('cart.store'), ['product_id' => menuItem(['price' => 500])->id]);

    $this->travel(4)->hours();

    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false)->where('cart.count', 1));
    $this->post(route('checkout.store'), ['payment_method' => 'cash'])->assertSessionHasErrors(['customer_name', 'payment_method']);
});

it('can still be paid online, and takes a name when one is given', function () {
    scanShopCode($this->outlet);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->post(route('checkout.store'), ['customer_name' => 'Aina', 'customer_phone' => '012-345 6789', 'payment_method' => 'fpx']);

    $order = Order::query()->sole();
    expect($order->customer_name)->toBe('Aina')
        ->and($order->customer_phone)->toBe('60123456789')
        ->and($order->payment_method)->toBe(PaymentMethod::Fpx);
});

it('asks for online payment when paying in the shop is switched off', function () {
    app(Settings::class)->put(['payments.dine_in_pay_at_counter' => false]);
    scanShopCode($this->outlet);
    $this->post(route('cart.store'), ['product_id' => menuItem()->id]);

    $this->post(route('checkout.store'), ['payment_method' => 'cash'])->assertSessionHasErrors('payment_method');
    $this->post(route('checkout.store'), ['payment_method' => 'fpx'])->assertSessionHasNoErrors();

    expect(Order::query()->sole()->customer_name)->toBe('Walk-in');
});

it('leaves the counter when delivery is chosen, or on request', function () {
    DeliveryZone::factory()->create();

    scanShopCode($this->outlet);
    $this->put(route('cart.preferences'), ['type' => 'delivery']);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false)->where('cart.type', 'delivery'));

    scanShopCode($this->outlet);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', true)->where('cart.type', 'pickup'));

    $this->delete(route('shop.leave'));
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false)->where('cart.type', 'pickup'));
});

it('lets the last code scanned decide between a table and the counter', function () {
    $table = DiningTable::factory()->create(['name' => 'Table 2']);

    scanShopCode($this->outlet);
    $this->get(route('tables.show', $table->token));
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.table.name', 'Table 2')->where('cart.at_counter', false)->where('cart.type', 'dine_in'));

    scanShopCode($this->outlet);
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.table', null)->where('cart.at_counter', true)->where('cart.type', 'pickup'));
});

it('refuses an unknown code, an inactive outlet and an outlet without pickup', function () {
    $this->get(route('shop.show', 'nope000000000000'))->assertRedirect(route('menu.index'))->assertSessionHas('error');

    $branch = secondOutlet();
    $branch->update(['is_active' => false]);
    scanShopCode($branch)->assertSessionHas('error');
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false)->where('outlet.current.id', $this->outlet->id));

    $branch->update(['is_active' => true]);
    app(Settings::class)->put(['ordering.pickup_enabled' => false], $branch);
    scanShopCode($branch)->assertSessionHas('error');
    $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false));
});

it('ignores another outlet\'s code while the business runs as one outlet', function () {
    $branch = secondOutlet();
    app(Settings::class)->put(['outlets.multi_enabled' => false]);

    scanShopCode($branch)->assertSessionHas('error');
    scanShopCode($this->outlet)->assertSessionHas('success');
});

describe('with several outlets', function () {
    beforeEach(fn () => $this->branch = secondOutlet('Shah Alam'));

    it('orders from the outlet whose code was scanned', function () {
        scanShopCode($this->branch)->assertSessionHas('success');

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('outlet.current.name', 'Shah Alam')
            ->where('cart.at_counter', true));

        $this->post(route('cart.store'), ['product_id' => menuItem()->id]);
        $this->post(route('checkout.store'), ['payment_method' => 'cash'])->assertSessionHasNoErrors();

        expect(Order::query()->sole()->outlet_id)->toBe($this->branch->id);
    });

    it('is no longer at the counter after choosing another outlet', function () {
        scanShopCode($this->branch);
        $this->put(route('outlets.choose'), ['outlet_id' => $this->outlet->id]);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false));

        // Nor after going back to it by hand: only the code at the counter says "I am here".
        $this->put(route('outlets.choose'), ['outlet_id' => $this->branch->id]);
        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.at_counter', false));
    });
});

describe('managing the shop code', function () {
    it('shows the code with the tables and prints a poster', function () {
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page
            ->where('shopCode.url', route('shop.show', $this->outlet->qr_token))
            ->where('shopCode.usable', true)
            ->has('outlet.current.id'));

        $this->get(route('admin.shop-qr.print'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Tables/ShopPoster')
            ->where('shopCode.url', route('shop.show', $this->outlet->qr_token))
            ->has('shop.name')
            ->has('outlet.multi'));

        app(Settings::class)->put(['ordering.pickup_enabled' => false]);
        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page->where('shopCode.usable', false));
    });

    it('issues a new code, which stops the old one', function () {
        $old = $this->outlet->qr_token;

        $this->actingAs(userWithRole(UserRole::Admin))->post(route('admin.shop-qr.regenerate'))->assertSessionHas('success');

        $new = $this->outlet->refresh()->qr_token;
        expect($new)->not->toBe($old);

        auth()->logout();
        $this->get(route('shop.show', $old))->assertSessionHas('error');
        $this->get(route('shop.show', $new))->assertSessionHas('success');
    });

    it('lets a manager reissue the code of their own outlet only', function () {
        $branch = secondOutlet();
        $main = $this->outlet->qr_token;

        $this->actingAs(staffAt($branch, UserRole::Manager))->post(route('admin.shop-qr.regenerate'))->assertSessionHas('success');

        expect($this->outlet->refresh()->qr_token)->toBe($main)
            ->and($branch->refresh()->qr_token)->not->toBeNull();

        $this->get(route('admin.shop-qr.print'))->assertInertia(fn (Assert $page) => $page->where('shopCode.url', route('shop.show', $branch->qr_token)));
    });

    it('is not for counter or kitchen staff', function (UserRole $role) {
        $this->actingAs(userWithRole($role));

        $this->post(route('admin.shop-qr.regenerate'))->assertForbidden();
        $this->get(route('admin.shop-qr.print'))->assertForbidden();
    })->with([UserRole::Cashier, UserRole::Kitchen, UserRole::Customer]);
});
