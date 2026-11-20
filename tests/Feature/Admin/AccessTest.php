<?php

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

it('sends guests to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
});

it('forbids every non-admin role', function (UserRole $role) {
    $this->actingAs(userWithRole($role));

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.products.index'))->assertForbidden();
    $this->put(route('admin.settings.update'), [])->assertForbidden();
})->with([UserRole::Customer, UserRole::Cashier, UserRole::Kitchen, UserRole::Rider]);

it('forbids a deactivated admin', function () {
    $admin = userWithRole(UserRole::Admin);
    $admin->update(['is_active' => false]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
});

it('renders every admin page', function (string $route, string $component) {
    // Give the pages something to show: a paid order, a cash order and a delivery.
    placeOrder(['method' => PaymentMethod::Fpx]);
    placeOrder();
    placeOrder(['type' => OrderType::Delivery]);

    $this->actingAs(userWithRole(UserRole::Admin))
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            // A page's own data must never hide the shared props the layout reads.
            ->has('outlet.current.id')
            ->has('outlet.multi')
            ->has('pos.label')
            ->has('kitchen.label')
            ->has('auth.user.role')
            ->has('shop.name'));
})->with([
    ['admin.dashboard', 'Admin/Dashboard'],
    ['admin.orders.index', 'Admin/Orders/Index'],
    ['admin.products.index', 'Admin/Products/Index'],
    ['admin.products.create', 'Admin/Products/Form'],
    ['admin.categories.index', 'Admin/Categories/Index'],
    ['admin.modifier-groups.index', 'Admin/ModifierGroups/Index'],
    ['admin.combo-offers.index', 'Admin/ComboOffers/Index'],
    ['admin.kitchen-stations.index', 'Admin/KitchenStations/Index'],
    ['admin.delivery-zones.index', 'Admin/DeliveryZones/Index'],
    ['admin.riders.index', 'Admin/Riders/Index'],
    ['admin.users.index', 'Admin/Users/Index'],
    ['admin.reports.index', 'Admin/Reports/Index'],
    ['admin.integrations.index', 'Admin/Integrations/Index'],
    ['admin.settings.edit', 'Admin/Settings/Edit'],
    ['admin.tables.index', 'Admin/Tables/Index'],
    ['admin.outlets.index', 'Admin/Outlets/Index'],
    ['admin.outlet.edit', 'Admin/Outlets/Edit'],
]);

it('shows today\'s numbers on the dashboard', function () {
    $paid = placeOrder(['method' => PaymentMethod::Fpx]);
    placeOrder(); // cash, not paid yet

    $this->actingAs(userWithRole(UserRole::Admin))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.sales', $paid->total)
            ->where('stats.orders', 1)
            ->where('stats.active', 2)
            ->has('chart', 14)
            ->where('chart.13.sales', $paid->total)
            ->has('topProducts', 1)
            ->has('recent', 2));
});
