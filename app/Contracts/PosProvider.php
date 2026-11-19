<?php

namespace App\Contracts;

use App\Data\PosCatalogItem;
use App\Data\PosReceipt;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * The point-of-sale system orders are pushed to (Loyverse).
 */
interface PosProvider
{
    public function name(): string;

    /**
     * Whether credentials are present so calls can be attempted.
     */
    public function isConfigured(): bool;

    /**
     * Check connectivity; returns a short human readable description of the
     * connected account or throws PosException.
     *
     * @throws \App\Exceptions\PosException
     */
    public function testConnection(): string;

    /**
     * Create a sales receipt for a confirmed order.
     *
     * @throws \App\Exceptions\PosException
     */
    public function pushOrder(Order $order): PosReceipt;

    /**
     * Every sellable variant in the POS catalogue.
     *
     * @return array<int, PosCatalogItem>
     *
     * @throws \App\Exceptions\PosException
     */
    public function catalog(): array;

    /**
     * Whether an incoming webhook request really came from the POS.
     */
    public function verifyWebhook(Request $request): bool;
}
