<?php

use App\Actions\Orders\CancelOrder;
use App\Contracts\PosProvider;
use App\Data\PosCatalogItem;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Exceptions\PosException;
use App\Models\PosSyncLog;
use App\Support\Settings;
use App\Support\ShopHours;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(userWithRole(UserRole::Admin));
});

/**
 * @param  array<string, array<string, mixed>>  $overrides
 * @return array<string, array<string, mixed>>
 */
function settingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'shop' => [
            'name' => 'Boss Burger HQ', 'tagline' => 'Night Grill', 'address' => '1 Jalan Besar', 'phone' => '+60 12-345 6789',
            'whatsapp' => '60123456789', 'maps_url' => 'https://maps.example.com/boss', 'counter' => 'Counter 5',
        ],
        'outlets' => ['multi_enabled' => false],
        'brand' => [
            'icon' => '🍜', 'primary_color' => '#0F766E', 'accent_color' => '#fde047',
            'footer_text' => 'Hand-pulled noodles since 1998.', 'badge_text' => '',
        ],
        'hours' => ['open' => '10:00', 'close' => '22:00', 'last_order' => '21:30'],
        'ordering' => [
            'enabled' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'dine_in_enabled' => true,
            'pickup_eta_min' => 10, 'pickup_eta_max' => 25, 'queue_prefix' => 'b', 'auto_close_hours' => 4,
        ],
        'payments' => [
            'fpx_enabled' => true, 'card_enabled' => true, 'ewallet_enabled' => false, 'cash_enabled' => true,
            'cash_requires_account' => false, 'cash_max_amount' => 8000, 'dine_in_pay_at_counter' => true,
        ],
        'pos' => ['quick_notes' => ['Pedas', ' Potong dua ', '']],
        'tax' => ['rate' => 8, 'inclusive' => false],
        'notifications' => ['enabled' => false],
    ], $overrides);
}

describe('settings', function () {
    it('saves settings and applies them', function () {
        $this->put(route('admin.settings.update'), settingsPayload())->assertSessionHas('success');

        $settings = app(Settings::class);

        expect($settings->string('shop.name'))->toBe('Boss Burger HQ')
            ->and($settings->string('shop.counter'))->toBe('Counter 5')
            ->and($settings->string('hours.last_order'))->toBe('21:30')
            ->and($settings->bool('ordering.delivery_enabled'))->toBeFalse()
            ->and($settings->int('ordering.pickup_eta_max'))->toBe(25)
            ->and($settings->string('ordering.queue_prefix'))->toBe('B')
            ->and($settings->get('tax.rate'))->toEqual(8)
            ->and($settings->bool('tax.inclusive'))->toBeFalse()
            ->and($settings->bool('notifications.enabled'))->toBeFalse();

        // New orders use the new prefix (placed at noon, inside the new opening hours).
        $this->travelTo(now()->setTime(12, 0));
        expect(placeOrder()->order_number)->toBe('B001');
    });

    it('can pause online ordering', function () {
        $this->put(route('admin.settings.update'), settingsPayload([
            'hours' => ['open' => '00:00', 'close' => '23:59', 'last_order' => '23:59'],
            'ordering' => ['enabled' => false],
        ]))->assertSessionHas('success');

        expect(app(ShopHours::class)->isAcceptingOrders())->toBeFalse();
    });

    it('validates settings', function () {
        $this->put(route('admin.settings.update'), settingsPayload([
            'shop' => ['name' => '', 'whatsapp' => '012-345', 'maps_url' => 'not-a-url'],
            'hours' => ['open' => '25:00'],
            'ordering' => ['pickup_eta_min' => 30, 'pickup_eta_max' => 10, 'queue_prefix' => 'A1'],
            'tax' => ['rate' => 120],
        ]))->assertSessionHasErrors([
            'shop.name', 'shop.whatsapp', 'shop.maps_url', 'hours.open', 'ordering.pickup_eta_max', 'ordering.queue_prefix', 'tax.rate',
        ]);

        expect(app(Settings::class)->string('shop.name'))->toBe('Boss Burger');
    });

    it('needs at least one way of ordering to stay on', function () {
        $this->put(route('admin.settings.update'), settingsPayload([
            'ordering' => ['pickup_enabled' => false, 'delivery_enabled' => false, 'dine_in_enabled' => false],
        ]))->assertSessionHasErrors('ordering.delivery_enabled');

        // A dine-in only shop is fine.
        $this->put(route('admin.settings.update'), settingsPayload([
            'ordering' => ['pickup_enabled' => false, 'delivery_enabled' => false, 'dine_in_enabled' => true],
        ]))->assertSessionHas('success');
    });

    it('shows the current settings grouped', function () {
        $this->get(route('admin.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.shop.name', 'Boss Burger')
                ->where('settings.tax.inclusive', true)
                ->has('settings.hours.open')
                ->has('settings.ordering.queue_prefix')
                ->has('settings.notifications.enabled'));
    });
});

describe('reports', function () {
    it('totals paid, non-cancelled orders in the period', function () {
        $paid = placeOrder(['method' => PaymentMethod::Fpx]);
        $delivery = placeOrder(['type' => OrderType::Delivery]);
        placeOrder(); // cash, unpaid: not counted
        $cancelled = placeOrder(['method' => PaymentMethod::Card]);
        app(CancelOrder::class)->handle($cancelled, 'Changed mind');

        $expected = $paid->total + $delivery->total;

        $this->get(route('admin.reports.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->where('totals.sales', $expected)
                ->where('totals.orders', 2)
                ->where('totals.average', (int) round($expected / 2))
                ->where('totals.delivery_fees', 500)
                ->where('totals.tax', $paid->tax_amount + $delivery->tax_amount)
                ->has('byDay', 1)
                ->where('byDay.0.sales', $expected)
                ->has('byProduct', 2)
                ->has('byPaymentMethod', 1)
                ->where('byPaymentMethod.0.label', 'FPX Online Banking')
                ->has('byType', 2)
                ->where('filters.to', now()->toDateString())
                ->where('filters.from', now()->subDays(6)->toDateString()));
    });

    it('respects the chosen date range', function () {
        placeOrder(['method' => PaymentMethod::Fpx]);
        $range = ['from' => now()->subDays(10)->toDateString(), 'to' => now()->subDays(5)->toDateString()];

        $this->get(route('admin.reports.index', $range))
            ->assertInertia(fn (Assert $page) => $page->where('totals.orders', 0)->where('filters.from', $range['from']));

        $this->get(route('admin.reports.index', ['from' => 'yesterday', 'to' => '2026-13-40']))
            ->assertSessionHasErrors(['from', 'to']);

        $this->get(route('admin.reports.index', ['from' => now()->toDateString(), 'to' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('to');
    });

    it('exports the orders as CSV', function () {
        $order = placeOrder(['method' => PaymentMethod::Fpx, 'quantity' => 2]);
        placeOrder(); // unpaid, excluded

        $response = $this->get(route('admin.reports.export'));

        $response->assertOk()->assertDownload('sales-'.now()->subDays(6)->toDateString().'-to-'.now()->toDateString().'.csv');
        expect($response->headers->get('content-type'))->toContain('text/csv');

        $rows = array_map('str_getcsv', array_filter(explode("\n", $response->streamedContent())));

        expect($rows)->toHaveCount(2)
            ->and($rows[0][0])->toBe('Order')
            ->and($rows[1][0])->toBe($order->order_number)
            ->and($rows[1][3])->toBe('Ahmad Faiz')
            ->and($rows[1][7])->toBe('2')
            ->and($rows[1][11])->toBe(number_format($order->total / 100, 2, '.', ''));
    });
});

describe('integrations', function () {
    it('shows the configured drivers and recent logs', function () {
        placeOrder(['method' => PaymentMethod::Fpx]);

        $this->get(route('admin.integrations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pos.provider', 'fake')
                ->where('pos.configured', true)
                ->where('pos.products_linked', 0)
                ->where('pos.products_unlinked', 1)
                ->where('payment.gateway', 'fake')
                ->where('messaging.channel', 'log')
                ->has('posLogs', 1)
                ->has('notificationLogs', 1));
    });

    it('tests the POS connection', function () {
        $this->post(route('admin.integrations.pos.test'))->assertSessionHas('success');

        $pos = Mockery::mock(PosProvider::class);
        $pos->shouldReceive('testConnection')->andThrow(new PosException('Bad token'));
        $this->app->instance(PosProvider::class, $pos);

        $this->post(route('admin.integrations.pos.test'))->assertSessionHas('error', 'POS connection failed: Bad token');
    });

    it('syncs the menu with the POS catalogue', function () {
        $product = menuItem(['code' => 'B1', 'name' => 'Smash Burger']);
        menuItem(['code' => 'ZZ', 'name' => 'Unknown Item']);

        $pos = Mockery::mock(PosProvider::class);
        $pos->shouldReceive('name')->andReturn('loyverse');
        $pos->shouldReceive('catalog')->andReturn([
            new PosCatalogItem('item-1', 'variant-1', 'Smash Burger', null, 'B1', 1500),
        ]);
        $this->app->instance(PosProvider::class, $pos);

        $this->post(route('admin.integrations.pos.sync-menu'))
            ->assertSessionHas('success', 'Linked 1 menu item(s) to the POS. 1 could not be matched — check the SKU or name in the POS.');

        expect($product->refresh()->pos_variant_id)->toBe('variant-1')
            ->and(PosSyncLog::query()->where('action', 'sync_menu')->count())->toBe(1);
    });

    it('flashes an error when the menu sync fails', function () {
        $pos = Mockery::mock(PosProvider::class);
        $pos->shouldReceive('catalog')->andThrow(new PosException('Loyverse is not configured.'));
        $this->app->instance(PosProvider::class, $pos);

        $this->post(route('admin.integrations.pos.sync-menu'))->assertSessionHas('error');
    });
});

it('saves payment rules and tidies the POS quick notes', function () {
    $this->put(route('admin.settings.update'), settingsPayload())->assertSessionHas('success');

    $settings = app(Settings::class);

    expect($settings->bool('payments.ewallet_enabled'))->toBeFalse()
        ->and($settings->int('payments.cash_max_amount'))->toBe(8000)
        ->and($settings->int('ordering.auto_close_hours'))->toBe(4)
        ->and($settings->get('pos.quick_notes'))->toBe(['Pedas', 'Potong dua']);
});

it('keeps at least one payment method on', function () {
    $this->put(route('admin.settings.update'), settingsPayload(['payments' => [
        'fpx_enabled' => false, 'card_enabled' => false, 'ewallet_enabled' => false, 'cash_enabled' => false,
    ]]))->assertSessionHasErrors('payments.cash_enabled');
});

describe('branding', function () {
    it('re-skins the storefront for a different shop', function () {
        $this->put(route('admin.settings.update'), settingsPayload())->assertSessionHas('success');

        $this->get(route('menu.index'))
            ->assertSee('--color-brand-500:#0f766e', false)
            ->assertSee('--color-flame-400:#fde047', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('shop.icon', '🍜')
                ->where('shop.footer_text', 'Hand-pulled noodles since 1998.')
                ->where('shop.badge_text', '')
                ->where('shop.logo_url', null));
    });

    it('refuses colours that would make text unreadable', function (string $key, string $colour) {
        $this->put(route('admin.settings.update'), settingsPayload(['brand' => [$key => $colour]]))
            ->assertSessionHasErrors("brand.{$key}");
    })->with([
        'main colour too light for white text' => ['primary_color', '#fde047'],
        'accent too dark for the dark background' => ['accent_color', '#1e293b'],
        'not a colour' => ['primary_color', 'red'],
    ]);

    it('uploads and removes a logo', function () {
        Storage::fake('public');

        $this->post(route('admin.settings.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertSessionHas('success');

        $path = app(Settings::class)->get('brand.logo_path');
        Storage::disk('public')->assertExists($path);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('shop.logo_url', fn ($url) => str_contains($url, $path)));

        $this->delete(route('admin.settings.logo.destroy'))->assertSessionHas('success');

        Storage::disk('public')->assertMissing($path);
        expect(app(Settings::class)->get('brand.logo_path'))->toBeNull();
    });

    it('rejects files that are not images', function () {
        $this->post(route('admin.settings.logo.store'), ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('logo');
    });
});
