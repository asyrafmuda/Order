<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OutletPriceRequest;
use App\Models\Category;
use App\Models\ComboOffer;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\OutletContext;
use App\Support\OutletPrices;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The prices of the outlet being viewed. The menu itself is shared; here a
 * super admin says what this outlet charges where it differs from the
 * standard price.
 */
class OutletPriceController extends Controller
{
    public function __construct(
        private readonly OutletPrices $prices,
        private readonly OutletContext $outlets,
    ) {}

    public function edit(): Response
    {
        // One outlet has one price list: the standard one, under Products.
        abort_unless($this->outlets->multi(), 404);

        $outlet = $this->outlet();
        $own = $this->prices->stored($outlet);

        $categories = Category::query()
            ->ordered()
            ->with(['products' => fn ($query) => $query->ordered()->with('variants')])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty());

        return Inertia::render('Admin/Outlets/Prices', [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'products' => $category->products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'price' => $product->price,
                    'outlet_price' => $own[OutletPrices::PRODUCT][$product->id] ?? null,
                    'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => $variant->price,
                        'outlet_price' => $own[OutletPrices::VARIANT][$variant->id] ?? null,
                    ])->all(),
                ])->all(),
            ])->values(),
            'modifierGroups' => ModifierGroup::query()->orderBy('sort_order')->orderBy('name')->with('modifiers')->get()
                ->filter(fn (ModifierGroup $group) => $group->modifiers->isNotEmpty())
                ->map(fn (ModifierGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'modifiers' => $group->modifiers->map(fn (Modifier $modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price' => $modifier->price,
                        'outlet_price' => $own[OutletPrices::MODIFIER][$modifier->id] ?? null,
                    ])->all(),
                ])->values(),
            'combos' => ComboOffer::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (ComboOffer $combo) => [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price,
                    'outlet_price' => $own[OutletPrices::COMBO][$combo->id] ?? null,
                ]),
        ]);
    }

    public function update(OutletPriceRequest $request): RedirectResponse
    {
        abort_unless($this->outlets->multi(), 404);

        $outlet = $this->outlet();
        $this->prices->save($outlet, $request->rows());

        $count = array_sum(array_map('count', $this->prices->stored($outlet)));

        return back()->with('success', $count === 0
            ? "{$outlet->name} now sells everything at the standard price."
            : "Prices saved. {$outlet->name} has its own price for {$count} ".($count === 1 ? 'item' : 'items').'.');
    }
}
