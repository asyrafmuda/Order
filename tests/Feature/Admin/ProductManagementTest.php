<?php

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(userWithRole(UserRole::Admin));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'category_id' => Category::factory()->create()->id,
        'kitchen_station_id' => null,
        'code' => 'B9',
        'name' => 'Volcano Burger',
        'description' => 'Hot.',
        'price' => 1890,
        'status' => ProductStatus::Available->value,
        'badge' => 'New',
        'tags' => ['Spicy', 'Brioche'],
        'is_combo_eligible' => true,
        'is_upsell' => false,
        'prep_minutes' => 12,
        'sort_order' => 3,
        'variants' => [],
        'modifier_group_ids' => [],
        ...$overrides,
    ];
}

it('creates a simple product', function () {
    $this->post(route('admin.products.store'), productPayload())
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::query()->where('slug', 'volcano-burger')->sole();

    expect($product->price)->toBe(1890)
        ->and($product->tags)->toBe(['Spicy', 'Brioche'])
        ->and($product->is_combo_eligible)->toBeTrue()
        ->and($product->variants)->toBeEmpty();
});

it('creates a product with sizes, options and a photo', function () {
    Storage::fake('public');
    $group = ModifierGroup::query()->create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 2]);

    $this->post(route('admin.products.store'), productPayload([
        'image' => UploadedFile::fake()->image('burger.jpg', 800, 600),
        'variants' => [
            ['id' => null, 'name' => 'Patty 150g', 'price' => 2090, 'is_default' => false],
            ['id' => null, 'name' => 'Patty 300g', 'price' => 2790, 'is_default' => true],
        ],
        'modifier_group_ids' => [$group->id],
    ]))->assertRedirect(route('admin.products.index'));

    $product = Product::query()->with(['variants', 'modifierGroups'])->where('name', 'Volcano Burger')->sole();

    expect($product->price)->toBe(2090) // follows the cheapest size
        ->and($product->variants->pluck('name')->all())->toBe(['Patty 150g', 'Patty 300g'])
        ->and($product->variants->firstWhere('is_default', true)->name)->toBe('Patty 300g')
        ->and($product->modifierGroups->pluck('id')->all())->toBe([$group->id])
        ->and($product->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($product->image_path);
});

it('updates a product while keeping the ids of edited sizes', function () {
    Storage::fake('public');
    $product = menuItem(['name' => 'Old Name', 'slug' => 'old-name', 'image_path' => 'products/old.jpg']);
    Storage::disk('public')->put('products/old.jpg', 'x');
    $kept = $product->variants()->create(['name' => 'Single', 'price' => 1390, 'is_default' => true, 'pos_variant_id' => 'pos-1']);
    $removed = $product->variants()->create(['name' => 'Triple', 'price' => 2990]);
    $group = ModifierGroup::query()->create(['name' => 'Sauce', 'min_select' => 1, 'max_select' => 1]);

    $this->put(route('admin.products.update', $product), productPayload([
        'category_id' => $product->category_id,
        'name' => 'New Name',
        'remove_image' => true,
        'variants' => [
            ['id' => $kept->id, 'name' => 'Single Fillet', 'price' => 1490, 'is_default' => true],
            ['id' => null, 'name' => 'Double Stack', 'price' => 1990, 'is_default' => false],
        ],
        'modifier_group_ids' => [$group->id],
    ]))->assertRedirect(route('admin.products.index'));

    $product->refresh()->load(['variants', 'modifierGroups']);

    expect($product->name)->toBe('New Name')
        ->and($product->slug)->toBe('new-name')
        ->and($product->price)->toBe(1490)
        ->and($product->image_path)->toBeNull()
        ->and($product->variants)->toHaveCount(2)
        ->and($product->variants->firstWhere('id', $kept->id)->name)->toBe('Single Fillet')
        ->and($product->variants->firstWhere('id', $kept->id)->pos_variant_id)->toBe('pos-1')
        ->and($product->variants->contains('id', $removed->id))->toBeFalse()
        ->and($product->modifierGroups)->toHaveCount(1);

    Storage::disk('public')->assertMissing('products/old.jpg');
});

it('gives products with the same name different slugs', function () {
    $this->post(route('admin.products.store'), productPayload());
    $this->post(route('admin.products.store'), productPayload());

    expect(Product::query()->pluck('slug')->all())->toBe(['volcano-burger', 'volcano-burger-2']);
});

it('validates product input', function () {
    $this->post(route('admin.products.store'), productPayload([
        'name' => '',
        'price' => -1,
        'category_id' => 999,
        'variants' => [['name' => '', 'price' => 'abc']],
    ]))->assertSessionHasErrors(['name', 'price', 'category_id', 'variants.0.name', 'variants.0.price']);

    expect(Product::query()->count())->toBe(0);
});

it('changes availability from the list', function () {
    $product = menuItem();

    $this->patch(route('admin.products.status', $product), ['status' => ProductStatus::SoldOut->value])
        ->assertSessionHas('success');

    expect($product->refresh()->status)->toBe(ProductStatus::SoldOut);

    $this->patch(route('admin.products.status', $product), ['status' => 'gone'])->assertSessionHasErrors('status');
});

it('soft deletes a product', function () {
    $product = menuItem();

    $this->delete(route('admin.products.destroy', $product))->assertSessionHas('success');

    expect($product->refresh()->trashed())->toBeTrue();
});

it('shows the edit form with the product loaded', function () {
    $product = menuItem();
    $product->variants()->create(['name' => 'Large', 'price' => 2000, 'is_default' => true]);

    $this->get(route('admin.products.edit', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Form')
            ->where('product.id', $product->id)
            ->has('product.variants', 1)
            ->where('product.modifier_group_ids', []));
});

it('filters the product list', function () {
    $burger = menuItem(['name' => 'Smash Burger', 'code' => 'B1']);
    menuItem(['name' => 'Fries', 'status' => ProductStatus::SoldOut]);

    $this->get(route('admin.products.index', ['search' => 'smash']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $burger->id));

    $this->get(route('admin.products.index', ['status' => 'sold_out']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Fries'));

    $this->get(route('admin.products.index', ['category' => $burger->category_id]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
});
