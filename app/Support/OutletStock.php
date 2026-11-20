<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * What each outlet has run out of. An item can be sold out in one branch and
 * still be on sale in the others; Product::$status stays the business-wide
 * switch an admin controls.
 */
class OutletStock
{
    /** @var array<int, array<int, int>> */
    private array $soldOut = [];

    public function __construct(private readonly OutletContext $context) {}

    /**
     * Ids of the products the outlet has marked sold out.
     *
     * @return array<int, int>
     */
    public function soldOutIds(?Outlet $outlet = null): array
    {
        $outlet ??= $this->context->current();

        return $this->soldOut[$outlet->id] ??= DB::table('outlet_sold_out_products')
            ->where('outlet_id', $outlet->id)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isSoldOut(Product|int $product, ?Outlet $outlet = null): bool
    {
        return in_array($product instanceof Product ? $product->id : $product, $this->soldOutIds($outlet), true);
    }

    /**
     * Whether the product can be ordered at the outlet right now.
     */
    public function isOrderable(Product $product, ?Outlet $outlet = null): bool
    {
        return $product->status->isOrderable() && ! $this->isSoldOut($product, $outlet);
    }

    public function mark(Product $product, bool $soldOut, ?Outlet $outlet = null): void
    {
        $outlet ??= $this->context->current();

        if ($soldOut) {
            DB::table('outlet_sold_out_products')->insertOrIgnore([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'created_at' => now(),
            ]);
        } else {
            DB::table('outlet_sold_out_products')->where('outlet_id', $outlet->id)->where('product_id', $product->id)->delete();
        }

        unset($this->soldOut[$outlet->id]);
    }
}
