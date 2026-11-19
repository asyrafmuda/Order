<?php

namespace App\Actions\Pos;

use App\Contracts\PosProvider;
use App\Data\PosCatalogItem;
use App\Exceptions\PosException;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Links local products to POS catalogue variants so receipts can be created.
 *
 * Matching order: SKU equals the product code (or "CODE-n" for the n-th
 * variant), then item name (+ variant name) ignoring case.
 */
class SyncPosMenu
{
    public function __construct(private readonly PosProvider $pos) {}

    /**
     * @return array{linked: int, unmatched: array<int, string>}
     *
     * @throws PosException
     */
    public function handle(): array
    {
        $catalog = collect($this->pos->catalog());
        $linked = 0;
        $unmatched = [];

        Product::query()->with('variants')->get()->each(function (Product $product) use ($catalog, &$linked, &$unmatched) {
            if ($product->variants->isEmpty()) {
                $match = $this->find($catalog, $product->code, $product->name, null);

                if ($match) {
                    $product->update(['pos_item_id' => $match->itemId, 'pos_variant_id' => $match->variantId]);
                    $linked++;
                } else {
                    $unmatched[] = $product->name;
                }

                return;
            }

            $product->variants->values()->each(function (ProductVariant $variant, int $index) use ($catalog, $product, &$linked, &$unmatched) {
                $sku = $product->code ? $product->code.'-'.($index + 1) : null;
                $match = $this->find($catalog, $sku, $product->name, $variant->name);

                if ($match) {
                    $variant->update(['pos_variant_id' => $match->variantId]);
                    $product->update(['pos_item_id' => $match->itemId]);
                    $linked++;
                } else {
                    $unmatched[] = "{$product->name} ({$variant->name})";
                }
            });
        });

        PosSyncLog::query()->create([
            'provider' => $this->pos->name(),
            'action' => 'sync_menu',
            'status' => 'synced',
            'response' => ['catalog_size' => $catalog->count(), 'linked' => $linked, 'unmatched' => $unmatched],
        ]);

        return ['linked' => $linked, 'unmatched' => $unmatched];
    }

    /**
     * @param  Collection<int, PosCatalogItem>  $catalog
     */
    private function find(Collection $catalog, ?string $sku, string $name, ?string $variantName): ?PosCatalogItem
    {
        if ($sku) {
            $bySku = $catalog->first(fn (PosCatalogItem $item) => $item->sku !== null && Str::lower($item->sku) === Str::lower($sku));

            if ($bySku) {
                return $bySku;
            }
        }

        return $catalog->first(fn (PosCatalogItem $item) => Str::lower($item->itemName) === Str::lower($name)
            && ($variantName === null || Str::lower((string) $item->variantName) === Str::lower($variantName)));
    }
}
