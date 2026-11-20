<?php

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Outlet;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->main = Outlet::default();
    $this->branch = secondOutlet('Shah Alam');
    $this->quiet = secondOutlet('Klang');

    // Paid online: RM 20 + RM 30 at the branch, RM 10 at the main outlet, nothing in Klang.
    $paid = ['method' => PaymentMethod::Fpx];
    placeOrder([...$paid, 'outlet' => $this->branch, 'products' => [menuItem(['price' => 2000, 'name' => 'Burger'])]]);
    placeOrder([...$paid, 'outlet' => $this->branch, 'products' => [menuItem(['price' => 3000, 'name' => 'Platter'])]]);
    placeOrder([...$paid, 'outlet' => $this->main, 'products' => [menuItem(['price' => 1000, 'name' => 'Fries'])]]);
    // Unpaid cash order: not in any report.
    placeOrder(['outlet' => $this->main]);

    $this->actingAs($this->admin = userWithRole(UserRole::Admin));
});

it('reports on the outlet being viewed by default', function () {
    $this->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page
        ->where('scope.all', false)
        ->where('scope.can_all', true)
        ->where('scope.outlet', $this->main->name)
        ->where('filters.outlet', '')
        ->where('totals.orders', 1)
        ->where('totals.sales', 1000)
        ->has('byOutlet', 0));
});

it('adds every outlet together for a super admin', function () {
    $this->get(route('admin.reports.index', ['outlet' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('scope.all', true)
        ->where('filters.outlet', 'all')
        ->where('totals.orders', 3)
        ->where('totals.sales', 6000)
        ->where('totals.average', 2000)
        ->has('byDay', 1)
        ->where('byDay.0.orders', 3)
        ->where('byDay.0.sales', 6000)
        ->has('byProduct', 3)
        ->where('byProduct.0.name', 'Platter')
        ->where('byPaymentMethod.0.orders', 3)
        ->where('byType.0.sales', 6000)
        // Best outlet first; an outlet with no sales is still listed.
        ->has('byOutlet', 3)
        ->where('byOutlet.0.name', 'Shah Alam')
        ->where('byOutlet.0.orders', 2)
        ->where('byOutlet.0.sales', 5000)
        ->where('byOutlet.0.average', 2500)
        ->where('byOutlet.1.id', $this->main->id)
        ->where('byOutlet.1.sales', 1000)
        ->where('byOutlet.2.name', 'Klang')
        ->where('byOutlet.2.orders', 0)
        ->where('byOutlet.2.sales', 0)
        ->where('byOutlet.2.average', 0));
});

it('keeps the combined view while the outlet in the header changes', function () {
    $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id]);

    $this->get(route('admin.reports.index', ['outlet' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('scope.outlet', 'Shah Alam')
        ->where('totals.sales', 6000));

    $this->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page->where('totals.sales', 5000));
});

it('respects the date range in the combined report', function () {
    $this->travel(3)->days();
    placeOrder(['method' => PaymentMethod::Fpx, 'outlet' => $this->quiet, 'products' => [menuItem(['price' => 500])]]);
    $today = now()->toDateString();

    $this->get(route('admin.reports.index', ['outlet' => 'all', 'from' => $today, 'to' => $today]))->assertInertia(fn (Assert $page) => $page
        ->where('totals.sales', 500)
        ->where('byOutlet.0.name', 'Klang')
        ->where('byOutlet.1.sales', 0));
});

it('exports every outlet with an outlet column', function () {
    $response = $this->get(route('admin.reports.export', ['outlet' => 'all']))->assertOk();
    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

    expect($response->headers->get('content-disposition'))->toContain('sales-all-outlets-')
        ->and($rows[0][0])->toBe('Outlet')
        ->and($rows)->toHaveCount(4)
        ->and(array_column(array_slice($rows, 1), 0))->toEqualCanonicalizing(['Shah Alam', 'Shah Alam', $this->main->name]);

    // The single-outlet export keeps its old columns.
    $single = array_map('str_getcsv', array_filter(explode("\n", trim($this->get(route('admin.reports.export'))->streamedContent()))));
    expect($single[0][0])->toBe('Order')->and($single)->toHaveCount(2);
});

it('never shows a manager other outlets, whatever they ask for', function () {
    $this->actingAs(staffAt($this->branch, UserRole::Manager));

    $this->get(route('admin.reports.index', ['outlet' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('scope.all', false)
        ->where('scope.can_all', false)
        ->where('totals.sales', 5000)
        ->has('byOutlet', 0));

    $rows = array_filter(explode("\n", trim($this->get(route('admin.reports.export', ['outlet' => 'all']))->streamedContent())));
    expect($rows)->toHaveCount(3)->and($rows[0])->toStartWith('Order');
});

it('has no combined view for a single-outlet business', function () {
    app(Settings::class)->put(['outlets.multi_enabled' => false]);

    $this->get(route('admin.reports.index', ['outlet' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('scope.all', false)
        ->where('scope.can_all', false)
        ->where('totals.sales', 1000));
});

it('rejects an unknown outlet filter', function () {
    $this->get(route('admin.reports.index', ['outlet' => 'everything']))->assertSessionHasErrors('outlet');
});
