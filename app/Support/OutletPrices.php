<?php

namespace App\Support;

use App\Models\ComboOffer;
use App\Models\Modifier;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Prices an outlet has set for itself. The menu is shared by every outlet;
 * an outlet may charge its own price for a product, a size, an add-on or the
 * combo upgrade, and anything it has not priced sells at the standard price.
 *
 * Selling screens (menu, cart, POS) call apply() on the models they loaded,
 * so the rest of the code keeps reading $product->price. Admin screens that
 * edit the standard prices never call it.
 */
class OutletPrices
{
    public const PRODUCT = 'product';

    public const VARIANT = 'variant';

    public const MODIFIER = 'modifier';

    public const COMBO = 'combo';

    public const TYPES = [self::PRODUCT, self::VARIANT, self::MODIFIER, self::COMBO];

    /** @var array<int, array<string, array<int, int>>> */
    private array $loaded = [];

    public function __construct(private readonly OutletContext $context) {}

    /**
     * The outlet's own prices as [type => [item id => sen]]. Empty while the
     * business runs as a single outlet.
     *
     * @return array<string, array<int, int>>
     */
    public function overrides(?Outlet $outlet = null): array
    {
        if (! $this->context->multi()) {
            return [];
        }

        return $this->stored($outlet ?? $this->context->current());
    }

    /**
     * What was saved for the outlet, whether or not multi-outlet is on.
     *
     * @return array<string, array<int, int>>
     */
    public function stored(Outlet $outlet): array
    {
        return $this->loaded[$outlet->id] ??= DB::table('outlet_prices')
            ->where('outlet_id', $outlet->id)
            ->get(['item_type', 'item_id', 'price'])
            ->groupBy('item_type')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [(int) $row->item_id => (int) $row->price])->all())
            ->all();
    }

    /**
     * Put the outlet's prices on the given products, and on their sizes and
     * add-ons when those are loaded.
     *
     * @param  iterable<Product>|Product  $products
     */
    public function apply(iterable|Product $products, ?Outlet $outlet = null): void
    {
        $prices = $this->overrides($outlet);

        if ($prices === []) {
            return;
        }

        foreach ($products instanceof Product ? [$products] : $products as $product) {
            $this->set($product, $prices[self::PRODUCT][$product->id] ?? null);

            if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $this->set($variant, $prices[self::VARIANT][$variant->id] ?? null);
                }

                // A product with sizes is priced "from" its cheapest one.
                $this->set($product, (int) $product->variants->min('price'));
            }

            if ($product->relationLoaded('modifierGroups')) {
                foreach ($product->modifierGroups as $group) {
                    foreach ($group->relationLoaded('modifiers') ? $group->modifiers : [] as $modifier) {
                        $this->set($modifier, $prices[self::MODIFIER][$modifier->id] ?? null);
                    }
                }
            }
        }
    }

    /**
     * The combo on offer, at this outlet's price.
     */
    public function currentCombo(?Outlet $outlet = null): ?ComboOffer
    {
        $combo = ComboOffer::current();

        if ($combo) {
            $this->set($combo, $this->overrides($outlet)[self::COMBO][$combo->id] ?? null);
        }

        return $combo;
    }

    /**
     * Save the outlet's prices. A null price, or one equal to the standard
     * price, means "follow the standard price" and removes the override.
     *
     * @param  array<int, array{type: string, id: int, price: int|null}>  $rows
     */
    public function save(Outlet $outlet, array $rows): void
    {
        $standard = $this->standardPrices();

        DB::transaction(function () use ($outlet, $rows, $standard) {
            foreach ($rows as $row) {
                $key = ['outlet_id' => $outlet->id, 'item_type' => $row['type'], 'item_id' => $row['id']];
                $base = $standard[$row['type']][$row['id']] ?? null;

                if ($row['price'] === null || $base === null || $row['price'] === $base) {
                    DB::table('outlet_prices')->where($key)->delete();

                    continue;
                }

                DB::table('outlet_prices')->updateOrInsert($key, ['price' => $row['price'], 'created_at' => now(), 'updated_at' => now()]);
            }

            // Prices of items that have since been removed from the menu.
            foreach ($standard as $type => $known) {
                DB::table('outlet_prices')
                    ->where('outlet_id', $outlet->id)
                    ->where('item_type', $type)
                    ->whereNotIn('item_id', array_keys($known))
                    ->delete();
            }
        });

        unset($this->loaded[$outlet->id]);
    }

    /**
     * The standard price of everything an outlet can price, as
     * [type => [item id => sen]]. Products that come in sizes are priced
     * through their sizes, so they are left out of "product".
     *
     * @return array<string, array<int, int>>
     */
    public function standardPrices(): array
    {
        $ints = fn ($prices) => $prices->map(fn ($price) => (int) $price)->all();

        return [
            self::PRODUCT => $ints(Product::query()->whereDoesntHave('variants')->pluck('price', 'id')),
            self::VARIANT => $ints(ProductVariant::query()->whereHas('product')->pluck('price', 'id')),
            self::MODIFIER => $ints(Modifier::query()->pluck('price', 'id')),
            self::COMBO => $ints(ComboOffer::query()->pluck('price', 'id')),
        ];
    }

    /**
     * Change the price on the loaded model only. It is marked as unchanged so
     * that saving the model later can never write an outlet's price over the
     * standard one.
     */
    private function set(Model $model, ?int $price): void
    {
        if ($price === null) {
            return;
        }

        $model->setAttribute('price', $price);
        $model->syncOriginalAttribute('price');
    }
}
