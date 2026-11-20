<?php

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Rider;
use App\Models\User;
use App\Support\OutletContext;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function outletPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Subang Jaya', 'is_active' => true, 'sort_order' => 2,
        'shop' => ['address' => '9 Jalan SS15/4, Subang Jaya', 'phone' => '03-5555 1234', 'whatsapp' => '60355551234', 'maps_url' => null, 'counter' => 'Counter 2'],
        'hours' => ['open' => '00:00', 'close' => '23:59', 'last_order' => '23:59'],
        'ordering' => [
            'enabled' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'dine_in_enabled' => true,
            'pickup_eta_min' => 10, 'pickup_eta_max' => 20, 'queue_prefix' => 's',
        ],
    ], $overrides);
}

/**
 * The business-wide settings form, as the Settings page submits it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function businessSettings(array $overrides = []): array
{
    return array_replace_recursive([
        'shop' => [
            'name' => 'Boss Burger', 'tagline' => 'Night Grill', 'address' => '1 Jalan Besar', 'phone' => '+60 12-345 6789',
            'whatsapp' => '60123456789', 'maps_url' => null, 'counter' => 'Counter 5',
        ],
        'outlets' => ['multi_enabled' => true],
        'brand' => ['icon' => '🍔', 'primary_color' => '#B91C1C', 'accent_color' => '#F59E0B', 'footer_text' => '', 'badge_text' => ''],
        'hours' => ['open' => '00:00', 'close' => '23:59', 'last_order' => '23:59'],
        'ordering' => [
            'enabled' => true, 'pickup_enabled' => true, 'delivery_enabled' => true, 'dine_in_enabled' => true,
            'pickup_eta_min' => 10, 'pickup_eta_max' => 25, 'queue_prefix' => 'A', 'auto_close_hours' => 4,
        ],
        'payments' => [
            'fpx_enabled' => true, 'card_enabled' => true, 'ewallet_enabled' => true, 'cash_enabled' => true,
            'cash_requires_account' => false, 'cash_max_amount' => 0, 'dine_in_pay_at_counter' => true,
        ],
        'pos' => ['quick_notes' => []],
        'tax' => ['rate' => 6, 'inclusive' => true],
        'notifications' => ['enabled' => false],
    ], $overrides);
}

describe('the multi-outlet switch', function () {
    beforeEach(fn () => $this->actingAs(userWithRole(UserRole::Admin)));

    it('is off on a fresh install, with one main outlet', function () {
        expect(Outlet::query()->count())->toBe(1)
            ->and(Outlet::default()->is_default)->toBeTrue()
            ->and(app(OutletContext::class)->multi())->toBeFalse();

        $this->get(route('admin.settings.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('multi', false)
            ->where('settings.outlets.multi_enabled', false)
            ->where('outlet.multi', false));
    });

    it('switches on from settings and keeps the main outlet\'s details', function () {
        $this->put(route('admin.settings.update'), businessSettings(['shop' => ['address' => '1 Jalan Besar'], 'outlets' => ['multi_enabled' => false]]))
            ->assertSessionHas('success');
        $this->put(route('admin.settings.update'), businessSettings())->assertSessionHas('success');

        expect(app(OutletContext::class)->multi())->toBeTrue()
            ->and(app(Settings::class)->forOutlet(Outlet::default())['shop.address'])->toBe('1 Jalan Besar');

        $this->get(route('admin.settings.edit'))->assertInertia(fn (Assert $page) => $page->where('multi', true)->where('outlet.multi', true));
    });

    it('leaves outlet details alone when business settings are saved in multi-outlet mode', function () {
        $branch = secondOutlet();
        app(Settings::class)->put(['shop.address' => 'Main address', 'hours.open' => '09:00'], Outlet::default());
        app(Settings::class)->put(['shop.address' => 'Branch address'], $branch);

        // The settings page no longer shows the outlet fields, so they are not even sent.
        $payload = businessSettings(['shop' => ['name' => 'Renamed Co']]);
        unset($payload['hours'], $payload['shop']['address'], $payload['shop']['phone'], $payload['shop']['whatsapp'], $payload['shop']['counter'], $payload['shop']['maps_url']);
        foreach (['enabled', 'pickup_enabled', 'delivery_enabled', 'dine_in_enabled', 'pickup_eta_min', 'pickup_eta_max', 'queue_prefix'] as $key) {
            unset($payload['ordering'][$key]);
        }

        $this->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');

        // And stale values that are sent anyway (an old tab) are ignored.
        $this->put(route('admin.settings.update'), businessSettings(['shop' => ['name' => 'Renamed Co', 'address' => 'Overwritten?'], 'hours' => ['open' => '03:00']]))
            ->assertSessionHas('success');

        $settings = app(Settings::class);
        expect($settings->string('shop.name'))->toBe('Renamed Co')
            ->and($settings->forOutlet(Outlet::default())['shop.address'])->toBe('Main address')
            ->and($settings->forOutlet(Outlet::default())['hours.open'])->toBe('09:00')
            ->and($settings->forOutlet($branch)['shop.address'])->toBe('Branch address');
    });

    it('cannot be switched off while another outlet still has open orders', function () {
        $branch = secondOutlet();
        $order = placeOrder(['outlet' => $branch]);

        $this->put(route('admin.settings.update'), businessSettings(['outlets' => ['multi_enabled' => false]]))
            ->assertSessionHasErrors('outlets.multi_enabled');
        expect(app(OutletContext::class)->multi())->toBeTrue();

        app(CancelOrder::class)->handle($order, 'Closing the branch');

        $this->put(route('admin.settings.update'), businessSettings(['outlets' => ['multi_enabled' => false]]))->assertSessionHas('success');
        expect(app(OutletContext::class)->multi())->toBeFalse();
    });

    it('puts everyone back in the main outlet when switched off, and keeps the other outlets for later', function () {
        $branch = secondOutlet();
        $cashier = staffAt($branch, UserRole::Cashier);
        $mainOrder = placeOrder();
        app(Settings::class)->put(['outlets.multi_enabled' => false]);

        $this->actingAs($cashier)->get(route('counter.index'))->assertInertia(fn (Assert $page) => $page
            ->where('outlet.multi', false)
            ->where('outlet.current.id', Outlet::default()->id)
            ->has('kitchenOrders', 1)
            ->where('kitchenOrders.0.uuid', $mainOrder->uuid));

        expect(Outlet::query()->count())->toBe(2)->and($cashier->refresh()->outlet_id)->toBe($branch->id);
    });
});

describe('super admin', function () {
    beforeEach(function () {
        $this->main = Outlet::default();
        $this->branch = secondOutlet('Shah Alam');
        $this->actingAs($this->admin = userWithRole(UserRole::Admin));
    });

    it('lists every outlet with today\'s numbers', function () {
        placeOrder(['outlet' => $this->branch]);
        placeOrder(['outlet' => $this->branch]);
        placeOrder(['outlet' => $this->main]);
        staffAt($this->branch, UserRole::Cashier);
        userWithRole(UserRole::Kitchen); // never assigned: works at the main outlet
        DiningTable::factory()->create(['outlet_id' => $this->branch->id]);

        $this->get(route('admin.outlets.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Outlets/Index')
            ->where('multi', true)
            ->has('outlets', 2)
            ->where('outlets.0.is_default', true)
            ->where('outlets.0.orders_today', 1)
            ->where('outlets.0.staff_count', 1)
            ->where('outlets.1.name', 'Shah Alam')
            ->where('outlets.1.orders_today', 2)
            ->where('outlets.1.staff_count', 1)
            ->where('outlets.1.tables_count', 1)
            ->has('outlets.1.settings.shop.address'));
    });

    it('adds an outlet with its own details', function () {
        $this->post(route('admin.outlets.store'), outletPayload())->assertSessionHasNoErrors()->assertSessionHas('success');

        $outlet = Outlet::query()->where('name', 'Subang Jaya')->sole();
        $settings = app(Settings::class)->forOutlet($outlet);

        expect($outlet->slug)->toBe('subang-jaya')
            ->and($outlet->is_default)->toBeFalse()
            ->and($settings['shop.address'])->toBe('9 Jalan SS15/4, Subang Jaya')
            ->and($settings['ordering.delivery_enabled'])->toBeFalse()
            ->and($settings['ordering.queue_prefix'])->toBe('S')
            // The other outlets are untouched.
            ->and(app(Settings::class)->forOutlet($this->main)['ordering.queue_prefix'])->toBe('A');

        expect(placeOrder(['outlet' => $outlet])->order_number)->toBe('S001');
    });

    it('validates an outlet', function () {
        $this->post(route('admin.outlets.store'), outletPayload(['name' => 'Shah Alam']))->assertSessionHasErrors('name');

        $this->post(route('admin.outlets.store'), outletPayload([
            'shop' => ['address' => '', 'whatsapp' => '012-345'],
            'hours' => ['open' => '25:00'],
            'ordering' => ['pickup_enabled' => false, 'dine_in_enabled' => false, 'pickup_eta_min' => 30, 'pickup_eta_max' => 10],
        ]))->assertSessionHasErrors(['shop.address', 'shop.whatsapp', 'hours.open', 'ordering.delivery_enabled', 'ordering.pickup_eta_max']);

        expect(Outlet::query()->count())->toBe(2);
    });

    it('edits and deactivates an outlet, but never the main one', function () {
        $this->put(route('admin.outlets.update', $this->branch), outletPayload(['name' => 'Shah Alam Seksyen 7', 'is_active' => false]))
            ->assertSessionHas('success');

        expect($this->branch->refresh()->name)->toBe('Shah Alam Seksyen 7')->and($this->branch->is_active)->toBeFalse();

        $this->put(route('admin.outlets.update', $this->main), outletPayload(['name' => 'HQ', 'is_active' => false]))
            ->assertSessionHasErrors('is_active');
        expect($this->main->refresh()->is_active)->toBeTrue();
    });

    it('removes an unused outlet with its tables and delivery areas, and keeps one with history', function () {
        DiningTable::factory()->create(['outlet_id' => $this->branch->id]);
        DeliveryZone::factory()->create(['outlet_id' => $this->branch->id]);
        $cashier = staffAt($this->branch, UserRole::Cashier);

        $this->delete(route('admin.outlets.destroy', $this->main))->assertSessionHas('error');

        $this->delete(route('admin.outlets.destroy', $this->branch))->assertSessionHas('success');
        expect(Outlet::query()->count())->toBe(1)
            ->and(DiningTable::query()->count())->toBe(0)
            ->and(DeliveryZone::query()->count())->toBe(0)
            // Its staff fall back to the main outlet rather than losing their account.
            ->and($cashier->refresh()->outlet_id)->toBeNull();

        $busy = secondOutlet('Klang');
        placeOrder(['outlet' => $busy]);

        $this->delete(route('admin.outlets.destroy', $busy))->assertSessionHas('error');
        expect($busy->fresh())->not->toBeNull();
    });

    it('switches between outlets in the back office', function () {
        placeOrder(['outlet' => $this->branch]);

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('outlet.current.id', $this->main->id)->where('outlet.can_switch', true)->has('outlet.options', 2)->where('stats.active', 0));

        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id])->assertSessionHas('success');

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('outlet.current.name', 'Shah Alam')->where('stats.active', 1));
        $this->get(route('admin.orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('kitchen.index'))->assertInertia(fn (Assert $page) => $page->has('orders', 1));

        $this->post(route('admin.outlets.switch'), ['outlet_id' => 999])->assertSessionHasErrors('outlet_id');
    });

    it('can work in an outlet that is closed to customers', function () {
        $this->branch->update(['is_active' => false]);
        app(OutletContext::class)->flush();

        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id])->assertSessionHas('success');
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('outlet.current.id', $this->branch->id));
    });

    it('follows an order to its outlet when opening it directly', function () {
        $order = placeOrder(['outlet' => $this->branch]);

        $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.uuid', $order->uuid)
            ->where('outlet.current.id', $this->branch->id));

        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Customer changed their mind'])->assertSessionHas('success');
        expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    });

    it('reports on one outlet at a time', function () {
        placeOrder(['outlet' => $this->branch, 'products' => [menuItem(['price' => 2000])]]);
        placeOrder(['outlet' => $this->main, 'products' => [menuItem(['price' => 500])]]);
        Order::query()->update(['payment_status' => 'paid']);

        $this->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page->where('totals.orders', 1)->where('totals.sales', 500));

        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id]);
        $this->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page->where('totals.orders', 1)->where('totals.sales', 2000));
        $this->get(route('admin.reports.export'))->assertOk();
    });

    it('assigns staff to an outlet', function () {
        $this->post(route('admin.users.store'), [
            'name' => 'Branch Cashier', 'email' => 'branch.cashier@example.com', 'phone' => null,
            'role' => 'cashier', 'is_active' => true, 'password' => 'secret-pass-1', 'outlet_id' => $this->branch->id,
        ])->assertSessionHas('success');

        $cashier = User::query()->where('email', 'branch.cashier@example.com')->sole();
        expect($cashier->outlet_id)->toBe($this->branch->id);

        $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page->where('multi', true)->has('outlets', 2));

        // Super admins and customers are never tied to an outlet.
        $this->put(route('admin.users.update', $cashier), [
            'name' => 'Branch Cashier', 'email' => 'branch.cashier@example.com', 'phone' => null,
            'role' => 'admin', 'is_active' => true, 'password' => '', 'outlet_id' => $this->branch->id,
        ])->assertSessionHas('success');
        expect($cashier->refresh()->outlet_id)->toBeNull();

        $this->post(route('admin.users.store'), [
            'name' => 'Nowhere', 'email' => 'nowhere@example.com', 'role' => 'kitchen', 'is_active' => true, 'password' => 'secret-pass-1', 'outlet_id' => 999,
        ])->assertSessionHasErrors('outlet_id');
    });

    it('manages tables, delivery areas and riders of the outlet being viewed', function () {
        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id]);

        $this->post(route('admin.tables.store'), ['name' => 'Table 1', 'seats' => 4, 'sort_order' => 1, 'is_active' => true])->assertSessionHas('success');
        $this->post(route('admin.riders.store'), [
            'name' => 'Rider Hakim', 'email' => 'hakim@example.com', 'phone' => '019-222 3333', 'password' => 'secret-pass-1',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'VAB 123', 'is_available' => true, 'is_active' => true,
        ])->assertSessionHas('success');

        expect(DiningTable::query()->sole()->outlet_id)->toBe($this->branch->id)
            ->and(Rider::query()->with('user')->sole()->user->outlet_id)->toBe($this->branch->id);

        // The same table name is fine at another outlet.
        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->main->id]);
        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page->has('tables', 0));
        $this->get(route('admin.riders.index'))->assertInertia(fn (Assert $page) => $page->has('riders', 0));
        $this->post(route('admin.tables.store'), ['name' => 'Table 1', 'seats' => 4, 'sort_order' => 1, 'is_active' => true])->assertSessionHasNoErrors();

        expect(DiningTable::query()->count())->toBe(2);
    });
});

describe('outlet manager', function () {
    beforeEach(function () {
        $this->main = Outlet::default();
        $this->branch = secondOutlet('Shah Alam');
        $this->actingAs($this->manager = staffAt($this->branch, UserRole::Manager));
    });

    it('lands on the dashboard of their own outlet', function () {
        placeOrder(['outlet' => $this->branch]);
        placeOrder(['outlet' => $this->main]);
        placeOrder(['outlet' => $this->main]);

        expect(UserRole::Manager->homeRoute())->toBe('admin.dashboard');

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('outlet.current.name', 'Shah Alam')
            ->where('outlet.can_switch', false)
            ->where('auth.user.role', 'manager')
            ->where('stats.active', 1));
    });

    it('runs their outlet but not the business', function (string $route, int $status) {
        $this->get(route($route))->assertStatus($status);
    })->with([
        ['admin.dashboard', 200], ['admin.orders.index', 200], ['admin.tables.index', 200], ['admin.delivery-zones.index', 200],
        ['admin.riders.index', 200], ['admin.reports.index', 200], ['admin.outlet.edit', 200],
        ['kitchen.index', 200], ['counter.index', 200], ['pos.index', 200],
        ['admin.products.index', 403], ['admin.categories.index', 403], ['admin.modifier-groups.index', 403], ['admin.combo-offers.index', 403],
        ['admin.kitchen-stations.index', 403], ['admin.users.index', 403], ['admin.outlets.index', 403],
        ['admin.integrations.index', 403], ['admin.settings.edit', 403],
    ]);

    it('cannot change business settings, outlets or other people', function () {
        $this->put(route('admin.settings.update'), businessSettings(['shop' => ['name' => 'Hijacked']]))->assertForbidden();
        $this->post(route('admin.outlets.store'), outletPayload())->assertForbidden();
        $this->put(route('admin.outlets.update', $this->main), outletPayload())->assertForbidden();
        $this->delete(route('admin.outlets.destroy', $this->main))->assertForbidden();
        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->main->id])->assertForbidden();
        $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass-1'])->assertForbidden();

        expect(app(Settings::class)->string('shop.name'))->not->toBe('Hijacked')->and(Outlet::query()->count())->toBe(2);
    });

    it('edits the details of their own outlet only', function () {
        $this->get(route('admin.outlet.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Outlets/Edit')
            ->where('outlet.id', $this->branch->id));

        $this->put(route('admin.outlet.update'), outletPayload([
            'name' => 'Renamed By Manager', 'is_active' => false,
            'shop' => ['address' => 'New branch address'], 'ordering' => ['delivery_enabled' => true],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $settings = app(Settings::class);
        expect($settings->forOutlet($this->branch)['shop.address'])->toBe('New branch address')
            ->and($settings->forOutlet($this->branch)['ordering.delivery_enabled'])->toBeTrue()
            // Renaming and closing an outlet is the super admin's call.
            ->and($this->branch->refresh()->name)->toBe('Shah Alam')
            ->and($this->branch->is_active)->toBeTrue()
            ->and($settings->forOutlet($this->main)['shop.address'])->not->toBe('New branch address');
    });

    it('cannot see or touch another outlet\'s records', function () {
        $order = placeOrder(['outlet' => $this->main]);
        $table = DiningTable::factory()->create();
        $zone = DeliveryZone::factory()->create();
        $rider = staffAt($this->main, UserRole::Rider)->rider;

        $this->get(route('admin.orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->get(route('admin.orders.show', $order))->assertNotFound();
        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Not mine to cancel'])->assertNotFound();
        $this->put(route('admin.tables.update', $table), ['name' => 'Taken', 'seats' => 2, 'sort_order' => 1, 'is_active' => true])->assertNotFound();
        $this->post(route('admin.tables.regenerate', $table))->assertNotFound();
        $this->delete(route('admin.tables.destroy', $table))->assertNotFound();
        $this->delete(route('admin.delivery-zones.destroy', $zone))->assertNotFound();
        $this->delete(route('admin.riders.destroy', $rider))->assertNotFound();

        expect($order->refresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($table->fresh())->not->toBeNull()
            ->and($zone->fresh())->not->toBeNull()
            ->and($rider->fresh())->not->toBeNull();
    });

    it('adds tables, delivery areas and riders to their own outlet', function () {
        $this->post(route('admin.tables.store'), ['name' => 'Table 1', 'seats' => 4, 'sort_order' => 1, 'is_active' => true])->assertSessionHas('success');
        $this->post(route('admin.delivery-zones.store'), [
            'name' => 'Seksyen 7', 'areas' => 'Seksyen 7, Seksyen 13', 'fee' => 4, 'min_order' => 15, 'eta_minutes' => 30, 'is_active' => true, 'sort_order' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('admin.riders.store'), [
            'name' => 'Rider Hakim', 'email' => 'hakim@example.com', 'phone' => '019-222 3333', 'password' => 'secret-pass-1',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'VAB 123', 'is_available' => true, 'is_active' => true,
        ])->assertSessionHas('success');

        expect(DiningTable::query()->sole()->outlet_id)->toBe($this->branch->id)
            ->and(DeliveryZone::query()->sole()->outlet_id)->toBe($this->branch->id)
            ->and(Rider::query()->with('user')->sole()->user->outlet_id)->toBe($this->branch->id);

        $this->get(route('admin.tables.print'))->assertInertia(fn (Assert $page) => $page->has('tables', 1));
    });
});

it('signs a manager in to the back office', function () {
    $manager = userWithRole(UserRole::Manager);

    $this->post(route('login'), ['login' => $manager->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
});

it('lets a manager run the single outlet when multi-outlet is off', function () {
    $this->actingAs(userWithRole(UserRole::Manager));

    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.outlet.edit'))->assertInertia(fn (Assert $page) => $page->where('outlet.is_default', true));
    $this->get(route('admin.settings.edit'))->assertForbidden();
});
