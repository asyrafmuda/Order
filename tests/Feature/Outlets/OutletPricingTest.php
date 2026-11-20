<?php

use App\Enums\UserRole;
use App\Models\ComboOffer;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Support\OutletPrices;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Give the outlet its own prices: [[type, id, sen], ...].
 *
 * @param  array<int, array{0: string, 1: int, 2: int|null}>  $rows
 */
function outletPrices(Outlet $outlet, array $rows): void
{
    app(OutletPrices::class)->save($outlet, array_map(fn (array $row) => ['type' => $row[0], 'id' => $row[1], 'price' => $row[2]], $rows));
}

/**
 * A menu item whose category sits at a known place on the menu.
 *
 * @param  array<string, mixed>  $attributes
 */
function menuItemAt(int $position, array $attributes = []): Product
{
    $product = menuItem($attributes);
    $product->category()->update(['sort_order' => $position]);

    return $product;
}

beforeEach(function () {
    $this->main = Outlet::default();
    $this->branch = secondOutlet('Airport');
});

describe('what customers pay', function () {
    it('shows and charges the outlet\'s own price', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);

        $this->post(route('cart.store'), ['product_id' => $burger->id]);
        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.products.0.price', 1500)
            ->where('categories.0.products.0.from_price', 1500)
            ->where('cart.lines.0.unit_price', 1500)
            ->where('cart.subtotal', 1500));

        // The same ticket costs more at the airport.
        $this->put(route('outlets.choose'), ['outlet_id' => $this->branch->id]);
        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.products.0.price', 1800)
            ->where('categories.0.products.0.from_price', 1800)
            ->where('cart.lines.0.unit_price', 1800)
            ->where('cart.subtotal', 1800));

        $this->post(route('checkout.store'), ['customer_name' => 'Aina', 'customer_phone' => '0123456789', 'payment_method' => 'cash']);

        $order = Order::query()->with('items')->sole();
        expect($order->outlet_id)->toBe($this->branch->id)
            ->and($order->subtotal)->toBe(1800)
            ->and($order->items->first()->unit_price)->toBe(1800);

        // The standard price on the menu is untouched.
        expect($burger->refresh()->price)->toBe(1500);
    });

    it('prices sizes, add-ons and the combo upgrade per outlet', function () {
        $burger = menuItem(['price' => 1000, 'is_combo_eligible' => true]);
        [$single, $double] = $burger->variants()->createMany([
            ['name' => 'Single', 'price' => 1000, 'is_default' => true, 'sort_order' => 0],
            ['name' => 'Double', 'price' => 1400, 'sort_order' => 1],
        ]);
        $group = ModifierGroup::query()->create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 2]);
        $cheese = $group->modifiers()->create(['name' => 'Cheese', 'price' => 200]);
        $egg = $group->modifiers()->create(['name' => 'Egg', 'price' => 150]);
        $burger->modifierGroups()->attach($group);
        $combo = ComboOffer::query()->create(['name' => 'Combo Set', 'price' => 790, 'choices' => ['Cola'], 'is_active' => true]);

        outletPrices($this->branch, [
            ['variant', $single->id, 1200],
            ['variant', $double->id, 1700],
            ['modifier', $cheese->id, 300],
            ['combo', $combo->id, 990],
        ]);

        $line = ['product_id' => $burger->id, 'variant_id' => $double->id, 'modifier_ids' => [$cheese->id, $egg->id], 'combo' => true, 'combo_choice' => 'Cola', 'quantity' => 2];

        $this->post(route('cart.store'), $line);
        // 1400 + 200 + 150 + 790
        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('combo.price', 790)
            ->where('categories.0.products.0.from_price', 1000)
            ->where('cart.lines.0.unit_price', 2540)
            ->where('cart.subtotal', 5080));

        $this->put(route('outlets.choose'), ['outlet_id' => $this->branch->id]);
        // 1700 + 300 + 150 (egg follows the standard price) + 990
        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('combo.price', 990)
            ->where('categories.0.products.0.from_price', 1200)
            ->where('categories.0.products.0.variants.0.price', 1200)
            ->where('categories.0.products.0.variants.1.price', 1700)
            ->where('categories.0.products.0.modifier_groups.0.modifiers.0.price', 300)
            ->where('categories.0.products.0.modifier_groups.0.modifiers.1.price', 150)
            ->where('cart.lines.0.unit_price', 3140)
            ->where('cart.lines.0.modifiers.0.price', 300)
            ->where('cart.lines.0.combo.price', 990)
            ->where('cart.subtotal', 6280));
    });

    it('rings up counter sales at the cashier\'s outlet price', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);
        $payload = ['type' => 'pickup', 'payment_method' => 'cash', 'paid' => true, 'lines' => [['product_id' => $burger->id, 'quantity' => 2]]];

        $this->actingAs(staffAt($this->branch, UserRole::Cashier));
        $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.price', 1800));
        $this->post(route('pos.orders.store'), $payload)->assertSessionHasNoErrors();

        $this->actingAs(staffAt($this->main, UserRole::Cashier));
        $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.price', 1500));
        $this->post(route('pos.orders.store'), $payload)->assertSessionHasNoErrors();

        expect(Order::query()->forOutlet($this->branch)->sole()->subtotal)->toBe(3600)
            ->and(Order::query()->forOutlet($this->main)->sole()->subtotal)->toBe(3000);
    });

    it('offers the upsell at the outlet price', function () {
        $burger = menuItem(['price' => 1500]);
        $fries = menuItem(['price' => 600, 'is_upsell' => true]);
        outletPrices($this->branch, [['product', $fries->id, 750]]);

        $this->put(route('outlets.choose'), ['outlet_id' => $this->branch->id]);
        $this->post(route('cart.store'), ['product_id' => $burger->id]);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('cart.upsells.0.price', 750));
    });

    it('ignores outlet prices while multi-outlet is switched off', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->main, [['product', $burger->id, 1700]]);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.price', 1700));

        app(Settings::class)->put(['outlets.multi_enabled' => false]);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.price', 1500));
        expect(placeOrder(['products' => [$burger]])->subtotal)->toBe(1500);
    });

    it('never writes an outlet price back to the menu', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);

        $loaded = $burger->fresh();
        app(OutletPrices::class)->apply($loaded, $this->branch);

        expect($loaded->price)->toBe(1800)->and($loaded->isDirty())->toBeFalse();

        $loaded->update(['name' => 'Renamed']);
        expect($burger->refresh()->price)->toBe(1500)->and($burger->name)->toBe('Renamed');
    });
});

describe('managing outlet prices', function () {
    beforeEach(function () {
        $this->actingAs($this->admin = userWithRole(UserRole::Admin));
        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->branch->id]);
    });

    it('lists the menu with the standard price and this outlet\'s price', function () {
        $burger = menuItemAt(1, ['price' => 1500, 'name' => 'Burger']);
        $sized = menuItemAt(2, ['price' => 1000, 'name' => 'Sized']);
        $large = $sized->variants()->create(['name' => 'Large', 'price' => 1400, 'is_default' => true]);
        $group = ModifierGroup::query()->create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 2]);
        $cheese = $group->modifiers()->create(['name' => 'Cheese', 'price' => 200]);
        ComboOffer::query()->create(['name' => 'Combo Set', 'price' => 790, 'choices' => [], 'is_active' => true]);
        outletPrices($this->branch, [['product', $burger->id, 1800], ['variant', $large->id, 1600], ['modifier', $cheese->id, 250]]);

        $this->get(route('admin.outlet-prices.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Outlets/Prices')
            ->where('outlet.current.name', 'Airport')
            ->where('outlet.can_switch', true)
            ->has('categories', 2)
            ->where('categories.0.products.0.price', 1500)
            ->where('categories.0.products.0.outlet_price', 1800)
            ->where('categories.1.products.0.variants.0.price', 1400)
            ->where('categories.1.products.0.variants.0.outlet_price', 1600)
            ->where('modifierGroups.0.modifiers.0.outlet_price', 250)
            ->where('combos.0.price', 790)
            ->where('combos.0.outlet_price', null));

        // Another outlet has none of these.
        $this->post(route('admin.outlets.switch'), ['outlet_id' => $this->main->id]);
        $this->get(route('admin.outlet-prices.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('outlet.current.id', $this->main->id)
            ->where('categories.0.products.0.outlet_price', null));
    });

    it('saves, changes and clears prices for the outlet being viewed', function () {
        $burger = menuItem(['price' => 1500]);
        $fries = menuItem(['price' => 600]);
        $drink = menuItem(['price' => 300]);

        $this->put(route('admin.outlet-prices.update'), ['prices' => [
            ['type' => 'product', 'id' => $burger->id, 'price' => 1800],
            ['type' => 'product', 'id' => $fries->id, 'price' => null],
            // Typing the standard price is the same as leaving it blank.
            ['type' => 'product', 'id' => $drink->id, 'price' => 300],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $prices = app(OutletPrices::class);
        expect($prices->stored($this->branch))->toBe(['product' => [$burger->id => 1800]])
            ->and($prices->stored($this->main))->toBe([]);

        $this->put(route('admin.outlet-prices.update'), ['prices' => [
            ['type' => 'product', 'id' => $burger->id, 'price' => null],
            ['type' => 'product', 'id' => $fries->id, 'price' => 0],
        ]])->assertSessionHas('success');

        // Free at this outlet is a real price, not "blank".
        expect($prices->stored($this->branch))->toBe(['product' => [$fries->id => 0]]);
    });

    it('keeps following the standard price for anything left blank', function () {
        $burger = menuItemAt(1, ['price' => 1500]);
        $fries = menuItemAt(2, ['price' => 600]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);

        $burger->update(['price' => 1600]);
        $fries->update(['price' => 700]);

        $this->get(route('menu.index'))->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.products.0.price', 1800)
            ->where('categories.1.products.0.price', 700));
    });

    it('rejects bad prices and items that are not on the menu', function () {
        $burger = menuItem(['price' => 1500]);
        $sized = menuItem(['price' => 1000]);
        $sized->variants()->create(['name' => 'Large', 'price' => 1400, 'is_default' => true]);

        $this->put(route('admin.outlet-prices.update'), ['prices' => [
            ['type' => 'product', 'id' => $burger->id, 'price' => -100],
            ['type' => 'product', 'id' => $burger->id, 'price' => 'abc'],
            ['type' => 'gift', 'id' => $burger->id, 'price' => 100],
        ]])->assertSessionHasErrors(['prices.0.price', 'prices.1.price', 'prices.2.type']);

        // An unknown item, and a product that is priced through its sizes.
        $this->put(route('admin.outlet-prices.update'), ['prices' => [
            ['type' => 'product', 'id' => 999999, 'price' => 100],
            ['type' => 'product', 'id' => $sized->id, 'price' => 100],
        ]])->assertSessionHasErrors(['prices.0.price', 'prices.1.price']);

        expect(DB::table('outlet_prices')->count())->toBe(0);
    });

    it('still edits the standard price on the product form while viewing an outlet with its own price', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);

        $this->get(route('admin.products.edit', $burger))->assertInertia(fn (Assert $page) => $page->where('product.price', 1500));
        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page->where('products.data.0.price', 1500));
    });

    it('forgets the prices of a removed outlet', function () {
        $burger = menuItem(['price' => 1500]);
        outletPrices($this->branch, [['product', $burger->id, 1800]]);

        $this->delete(route('admin.outlets.destroy', $this->branch))->assertSessionHas('success');

        expect(DB::table('outlet_prices')->count())->toBe(0);
    });

    it('is for super admins of a multi-outlet business only', function () {
        $burger = menuItem();
        $payload = ['prices' => [['type' => 'product', 'id' => $burger->id, 'price' => 100]]];

        $this->actingAs(staffAt($this->branch, UserRole::Manager));
        $this->get(route('admin.outlet-prices.edit'))->assertForbidden();
        $this->put(route('admin.outlet-prices.update'), $payload)->assertForbidden();

        app(Settings::class)->put(['outlets.multi_enabled' => false]);
        $this->actingAs($this->admin);
        $this->get(route('admin.outlet-prices.edit'))->assertNotFound();
        $this->put(route('admin.outlet-prices.update'), $payload)->assertNotFound();

        expect(DB::table('outlet_prices')->count())->toBe(0);
    });
});
