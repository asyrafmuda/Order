<?php

namespace App\Services\Cart;

use App\Enums\OrderType;
use App\Exceptions\InvalidCartLine;
use App\Models\ComboOffer;
use App\Models\DeliveryZone;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Support\OutletStock;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Resolves raw cart lines (ids + quantities) against the live menu.
 * Nothing sent by the browser is trusted for pricing.
 */
class CartPricer
{
    public function __construct(
        private readonly Settings $settings,
        private readonly OutletStock $stock,
    ) {}

    /**
     * Price the given raw lines. Lines that can no longer be sold are skipped
     * and their ids returned in $invalid so the caller can drop them.
     *
     * @param  array<int, array<string, mixed>>  $rawLines
     * @param  array<int, string>  $invalid
     */
    public function price(array $rawLines, OrderType $type, ?DeliveryZone $zone, array &$invalid = []): PricedCart
    {
        $products = $this->loadProducts($rawLines);
        $combo = ComboOffer::current();
        $lines = collect();

        foreach ($rawLines as $raw) {
            try {
                $lines->push($this->resolve($raw, $products->get($raw['product_id']), $combo));
            } catch (InvalidCartLine) {
                $invalid[] = $raw['id'];
            }
        }

        return new PricedCart(
            lines: $lines,
            type: $type,
            zone: $type === OrderType::Delivery ? $zone : null,
            taxRate: (float) $this->settings->get('tax.rate', 0),
            taxInclusive: $this->settings->bool('tax.inclusive'),
        );
    }

    /**
     * Resolve a single raw line or explain why it cannot be sold.
     *
     * @param  array<string, mixed>  $raw
     *
     * @throws InvalidCartLine
     */
    public function resolveLine(array $raw): PricedLine
    {
        return $this->resolve($raw, $this->loadProducts([$raw])->get($raw['product_id']), ComboOffer::current());
    }

    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws InvalidCartLine
     */
    private function resolve(array $raw, ?Product $product, ?ComboOffer $combo): PricedLine
    {
        // Sold out is per outlet: an item can be gone here and on sale elsewhere.
        if (! $product || ! $this->stock->isOrderable($product) || ! $product->category->is_active) {
            throw new InvalidCartLine(($product->name ?? 'This item').' is not available right now.');
        }

        $variant = null;

        if ($product->variants->isNotEmpty()) {
            $variant = $product->variants->firstWhere('id', $raw['variant_id'] ?? null);

            if (! $variant) {
                throw new InvalidCartLine("Please choose an option for {$product->name}.");
            }
        }

        $modifiers = $this->resolveModifiers($product, $raw['modifier_ids'] ?? []);

        $wantsCombo = (bool) ($raw['combo'] ?? false);

        if ($wantsCombo && (! $combo || ! $product->is_combo_eligible)) {
            throw new InvalidCartLine("{$product->name} cannot be upgraded to a combo.");
        }

        $comboChoice = null;

        if ($wantsCombo) {
            $choices = $combo->choices ?? [];
            $comboChoice = $raw['combo_choice'] ?? null;

            if ($choices !== [] && ! in_array($comboChoice, $choices, true)) {
                $comboChoice = $choices[0];
            }
        }

        return new PricedLine(
            id: $raw['id'],
            product: $product,
            variant: $variant,
            modifiers: $modifiers,
            combo: $wantsCombo ? $combo : null,
            comboChoice: $comboChoice,
            quantity: max(1, min(99, (int) ($raw['quantity'] ?? 1))),
            notes: $raw['notes'] ?? null,
        );
    }

    /**
     * @param  array<int, int>  $modifierIds
     * @return Collection<int, Modifier>
     *
     * @throws InvalidCartLine
     */
    private function resolveModifiers(Product $product, array $modifierIds): Collection
    {
        $modifierIds = array_map('intval', $modifierIds);
        $selected = collect();

        /** @var ModifierGroup $group */
        foreach ($product->modifierGroups as $group) {
            $chosen = $group->modifiers
                ->where('is_available', true)
                ->whereIn('id', $modifierIds)
                ->values();

            if ($chosen->count() < $group->min_select) {
                throw new InvalidCartLine("Please choose {$group->name} for {$product->name}.");
            }

            if ($chosen->count() > $group->max_select) {
                throw new InvalidCartLine("You can pick up to {$group->max_select} for {$group->name}.");
            }

            $selected = $selected->concat($chosen);
        }

        if ($selected->count() !== count(array_unique($modifierIds))) {
            throw new InvalidCartLine("One of the selected options is no longer available for {$product->name}.");
        }

        return $selected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return Collection<int, Product>
     */
    private function loadProducts(array $rawLines): Collection
    {
        return Product::query()
            ->with(['category', 'variants', 'modifierGroups.modifiers'])
            ->whereKey(array_unique(array_column($rawLines, 'product_id')))
            ->get()
            ->keyBy('id');
    }
}
