<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ComboOffer;
use App\Models\DeliveryZone;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;

beforeEach(function () {
    $this->actingAs(userWithRole(UserRole::Admin));
});

describe('categories', function () {
    it('creates and updates a category with a unique slug', function () {
        $station = KitchenStation::factory()->create();
        $payload = [
            'name' => 'Rice Bowls', 'icon' => '🍚', 'eyebrow' => 'Wok', 'tagline' => null,
            'kitchen_station_id' => $station->id, 'sort_order' => 4, 'is_active' => true,
        ];

        $this->post(route('admin.categories.store'), $payload)->assertSessionHas('success');
        $this->post(route('admin.categories.store'), $payload)->assertSessionHas('success');

        expect(Category::query()->pluck('slug')->all())->toBe(['rice-bowls', 'rice-bowls-2']);

        $category = Category::query()->firstOrFail();

        $this->put(route('admin.categories.update', $category), [...$payload, 'name' => 'Nasi', 'is_active' => false])
            ->assertSessionHas('success');

        expect($category->refresh()->slug)->toBe('nasi')
            ->and($category->is_active)->toBeFalse()
            ->and($category->kitchen_station_id)->toBe($station->id);
    });

    it('refuses to delete a category that still has products', function () {
        $product = menuItem();

        $this->delete(route('admin.categories.destroy', $product->category_id))->assertSessionHas('error');
        expect(Category::query()->whereKey($product->category_id)->exists())->toBeTrue();

        // Soft-deleted products still reference the category.
        $product->delete();
        $this->delete(route('admin.categories.destroy', $product->category_id))->assertSessionHas('error');
    });

    it('deletes an empty category', function () {
        $category = Category::factory()->create();

        $this->delete(route('admin.categories.destroy', $category))->assertSessionHas('success');

        expect(Category::query()->count())->toBe(0);
    });

    it('validates category input', function () {
        $this->post(route('admin.categories.store'), ['name' => '', 'sort_order' => -1, 'is_active' => 'maybe'])
            ->assertSessionHasErrors(['name', 'sort_order', 'is_active']);
    });
});

describe('kitchen stations', function () {
    it('creates, updates and deletes an unused station', function () {
        $this->post(route('admin.kitchen-stations.store'), ['name' => 'Smoker', 'sort_order' => 2, 'is_active' => true])
            ->assertSessionHas('success');

        $station = KitchenStation::query()->where('slug', 'smoker')->sole();

        $this->put(route('admin.kitchen-stations.update', $station), ['name' => 'Smoke Pit', 'sort_order' => 1, 'is_active' => false])
            ->assertSessionHas('success');

        expect($station->refresh()->slug)->toBe('smoke-pit')->and($station->is_active)->toBeFalse();

        $this->delete(route('admin.kitchen-stations.destroy', $station))->assertSessionHas('success');
        expect(KitchenStation::query()->count())->toBe(0);
    });

    it('refuses to delete a station that still cooks menu items', function () {
        $product = menuItem();
        $stationId = $product->category->kitchen_station_id;

        $this->delete(route('admin.kitchen-stations.destroy', $stationId))->assertSessionHas('error');

        expect(KitchenStation::query()->whereKey($stationId)->exists())->toBeTrue();
    });
});

describe('delivery zones', function () {
    it('manages delivery zones', function () {
        $payload = ['name' => 'Zone X', 'areas' => 'Far away', 'fee' => 1500, 'min_order' => 5000, 'eta_minutes' => 70, 'sort_order' => 0, 'is_active' => true];

        $this->post(route('admin.delivery-zones.store'), $payload)->assertSessionHas('success');
        $zone = DeliveryZone::query()->sole();
        expect($zone->fee)->toBe(1500)->and($zone->min_order)->toBe(5000);

        $this->put(route('admin.delivery-zones.update', $zone), [...$payload, 'fee' => 900, 'is_active' => false])->assertSessionHas('success');
        expect($zone->refresh()->fee)->toBe(900)->and($zone->is_active)->toBeFalse();

        $this->post(route('admin.delivery-zones.store'), [...$payload, 'fee' => -5, 'eta_minutes' => 1])
            ->assertSessionHasErrors(['fee', 'eta_minutes']);

        $this->delete(route('admin.delivery-zones.destroy', $zone))->assertSessionHas('success');
        expect(DeliveryZone::query()->count())->toBe(0);
    });
});

describe('combo offers', function () {
    it('keeps a single combo active', function () {
        $payload = ['name' => 'Combo Set', 'description' => 'Fries + drink', 'price' => 790, 'choices' => ['Cola', 'Lemon Tea'], 'is_active' => true];

        $this->post(route('admin.combo-offers.store'), $payload)->assertSessionHas('success');
        $this->post(route('admin.combo-offers.store'), [...$payload, 'name' => 'Mega Combo', 'price' => 990])->assertSessionHas('success');

        [$first, $second] = ComboOffer::query()->orderBy('id')->get();

        expect($first->is_active)->toBeFalse()
            ->and($second->is_active)->toBeTrue()
            ->and($second->choices)->toBe(['Cola', 'Lemon Tea'])
            ->and(ComboOffer::current()->id)->toBe($second->id);

        $this->put(route('admin.combo-offers.update', $first), [...$payload, 'choices' => []])->assertSessionHas('success');

        expect($first->refresh()->is_active)->toBeTrue()
            ->and($first->choices)->toBe([])
            ->and($second->refresh()->is_active)->toBeFalse();

        $this->delete(route('admin.combo-offers.destroy', $second))->assertSessionHas('success');
        expect(ComboOffer::query()->count())->toBe(1);
    });
});

describe('modifier groups', function () {
    it('creates a group with options and syncs them on update', function () {
        $this->post(route('admin.modifier-groups.store'), [
            'name' => 'Choice of Sauce', 'min_select' => 1, 'max_select' => 1, 'sort_order' => 0,
            'modifiers' => [
                ['id' => null, 'name' => 'Black Pepper', 'price' => 0, 'is_available' => true],
                ['id' => null, 'name' => 'Mushroom', 'price' => 100, 'is_available' => true],
            ],
        ])->assertSessionHas('success');

        $group = ModifierGroup::query()->with('modifiers')->sole();
        [$pepper, $mushroom] = $group->modifiers;
        $pepper->update(['pos_modifier_option_id' => 'pos-9']);

        $this->put(route('admin.modifier-groups.update', $group), [
            'name' => 'Sauce', 'min_select' => 0, 'max_select' => 2, 'sort_order' => 1,
            'modifiers' => [
                ['id' => $pepper->id, 'name' => 'Black Pepper Sauce', 'price' => 50, 'is_available' => false],
                ['id' => null, 'name' => 'Smoky BBQ', 'price' => 0, 'is_available' => true],
            ],
        ])->assertSessionHas('success');

        $group->refresh()->load('modifiers');

        expect($group->name)->toBe('Sauce')
            ->and($group->max_select)->toBe(2)
            ->and($group->modifiers->pluck('name')->all())->toBe(['Black Pepper Sauce', 'Smoky BBQ'])
            ->and($group->modifiers->first()->id)->toBe($pepper->id)
            ->and($group->modifiers->first()->pos_modifier_option_id)->toBe('pos-9')
            ->and($group->modifiers->first()->is_available)->toBeFalse()
            ->and($group->modifiers->contains('id', $mushroom->id))->toBeFalse();
    });

    it('validates the selection rule and options', function () {
        $this->post(route('admin.modifier-groups.store'), [
            'name' => 'Broken', 'min_select' => 3, 'max_select' => 1, 'sort_order' => 0, 'modifiers' => [],
        ])->assertSessionHasErrors(['max_select', 'modifiers']);
    });

    it('deletes a group and detaches it from products', function () {
        $product = menuItem();
        $group = ModifierGroup::query()->create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 1]);
        $group->modifiers()->create(['name' => 'Egg', 'price' => 150]);
        $product->modifierGroups()->attach($group);

        $this->delete(route('admin.modifier-groups.destroy', $group))->assertSessionHas('success');

        expect(ModifierGroup::query()->count())->toBe(0)
            ->and($product->modifierGroups()->count())->toBe(0);
    });
});
