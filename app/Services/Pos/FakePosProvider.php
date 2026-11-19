<?php

namespace App\Services\Pos;

use App\Contracts\PosProvider;
use App\Data\PosReceipt;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Local stand-in for the POS. Receipts are "created" instantly so the whole
 * order lifecycle can be exercised without a Loyverse account.
 */
class FakePosProvider implements PosProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): string
    {
        return 'Simulated POS (no external calls)';
    }

    public function pushOrder(Order $order): PosReceipt
    {
        return new PosReceipt(
            receiptNumber: sprintf('LV-%s%03d', $order->business_date->format('md'), $order->queue_number),
            request: ['order' => $order->order_number, 'total' => $order->total],
            response: ['simulated' => true],
        );
    }

    public function catalog(): array
    {
        return [];
    }

    public function verifyWebhook(Request $request): bool
    {
        return app()->environment('local', 'testing');
    }
}
