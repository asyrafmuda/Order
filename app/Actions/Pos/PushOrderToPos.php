<?php

namespace App\Actions\Pos;

use App\Contracts\PosProvider;
use App\Enums\SyncStatus;
use App\Events\OrderUpdated;
use App\Exceptions\PosException;
use App\Models\Order;

/**
 * Creates the POS receipt for an order and records the attempt.
 */
class PushOrderToPos
{
    public function __construct(private readonly PosProvider $pos) {}

    /**
     * @throws PosException
     */
    public function handle(Order $order): Order
    {
        if ($order->pos_sync_status === SyncStatus::Synced) {
            return $order;
        }

        try {
            $receipt = $this->pos->pushOrder($order);
        } catch (PosException $exception) {
            $order->update(['pos_sync_status' => SyncStatus::Failed]);
            $order->posSyncLogs()->create([
                'provider' => $this->pos->name(),
                'action' => 'push_order',
                'status' => SyncStatus::Failed->value,
                'error' => $exception->getMessage(),
            ]);

            OrderUpdated::dispatch($order);

            throw $exception;
        }

        $order->update([
            'pos_sync_status' => SyncStatus::Synced,
            'pos_receipt_number' => $receipt->receiptNumber,
            'pos_synced_at' => now(),
        ]);

        $order->posSyncLogs()->create([
            'provider' => $this->pos->name(),
            'action' => 'push_order',
            'status' => SyncStatus::Synced->value,
            'request' => $receipt->request,
            'response' => $receipt->response,
        ]);

        OrderUpdated::dispatch($order);

        return $order;
    }
}
