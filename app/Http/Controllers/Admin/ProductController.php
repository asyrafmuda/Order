<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Admin\Concerns\GeneratesUniqueSlugs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Support\Paginate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use GeneratesUniqueSlugs;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
        ]);

        $products = Product::query()
            ->with(['category', 'variants'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.trim($search).'%';

                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('category_id')
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => Paginate::through($products, ProductResource::class),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'status' => $filters['status'] ?? '',
            ],
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'statuses' => ProductStatus::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::query()->create([
                ...$request->productAttributes(),
                'slug' => $this->uniqueSlug(Product::class, $request->validated('name')),
                'image_path' => $request->file('image')?->store('products', 'public'),
            ]);

            $this->syncVariants($product, $request->validated('variants', []));
            $product->modifierGroups()->sync($request->validated('modifier_group_ids', []));

            return $product;
        });

        return redirect()->route('admin.products.index')->with('success', "{$product->name} was added to the menu.");
    }

    public function edit(Request $request, Product $product): Response
    {
        return $this->form($request, $product);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $oldImage = $product->image_path;

        DB::transaction(function () use ($request, $product) {
            $attributes = [
                ...$request->productAttributes(),
                'slug' => $this->uniqueSlug(Product::class, $request->validated('name'), $product->id),
            ];

            if ($request->hasFile('image')) {
                $attributes['image_path'] = $request->file('image')->store('products', 'public');
            } elseif ($request->boolean('remove_image')) {
                $attributes['image_path'] = null;
            }

            $product->update($attributes);

            $this->syncVariants($product, $request->validated('variants', []));
            $product->modifierGroups()->sync($request->validated('modifier_group_ids', []));
        });

        // Uploaded photos live on the public disk; bundled assets (paths starting with "/") are left alone.
        if ($oldImage && $oldImage !== $product->image_path && ! str_starts_with($oldImage, '/')) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('admin.products.index')->with('success', "{$product->name} was updated.");
    }

    /**
     * Quick availability switch from the product list (e.g. mark sold out).
     */
    public function status(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);

        $product->update(['status' => $validated['status']]);

        return back()->with('success', "{$product->name} is now ".strtolower($product->status->label()).'.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', "{$product->name} was removed from the menu.");
    }

    private function form(Request $request, ?Product $product): Response
    {
        $product?->load(['variants', 'modifierGroups']);

        return Inertia::render('Admin/Products/Form', [
            'product' => $product ? [
                ...(new ProductResource($product))->resolve($request),
                'modifier_group_ids' => $product->modifierGroups->pluck('id')->all(),
            ] : null,
            'categories' => Category::query()->ordered()->get(['id', 'name', 'kitchen_station_id']),
            'stations' => KitchenStation::query()->orderBy('sort_order')->get(['id', 'name']),
            'modifierGroups' => ModifierGroup::query()->withCount('modifiers')->orderBy('sort_order')->get()
                ->map(fn (ModifierGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'modifiers_count' => $group->modifiers_count,
                    'min_select' => $group->min_select,
                    'max_select' => $group->max_select,
                ]),
            'statuses' => ProductStatus::options(),
        ]);
    }

    /**
     * Replace the product's variants with the submitted list. Rows keep their
     * id (and with it the POS variant link) when they are only edited. A
     * product with variants is priced "from" its cheapest one.
     *
     * @param  array<int, array{id?: int|null, name: string, price: int, is_default?: bool}>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $variants = array_values($variants);
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];

        $defaultIndex = array_key_first(array_filter($variants, fn (array $variant) => (bool) ($variant['is_default'] ?? false))) ?? 0;

        foreach ($variants as $index => $variant) {
            $attributes = [
                'name' => $variant['name'],
                'price' => (int) $variant['price'],
                'is_default' => $index === $defaultIndex,
                'sort_order' => $index,
            ];

            $model = isset($variant['id']) ? $existing->get((int) $variant['id']) : null;

            if ($model) {
                $model->update($attributes);
            } else {
                $model = $product->variants()->create($attributes);
            }

            $kept[] = $model->id;
        }

        $product->variants()->whereNotIn('id', $kept)->delete();

        if ($variants !== []) {
            $product->update(['price' => min(array_map(fn (array $variant) => (int) $variant['price'], $variants))]);
        }
    }
}
