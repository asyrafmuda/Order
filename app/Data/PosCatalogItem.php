<?php

namespace App\Data;

/**
 * One sellable variant in the POS catalogue.
 */
final readonly class PosCatalogItem
{
    public function __construct(
        public string $itemId,
        public string $variantId,
        public string $itemName,
        public ?string $variantName,
        public ?string $sku,
        public ?int $price,
    ) {}
}
