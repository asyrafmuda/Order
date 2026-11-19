<?php

namespace App\Listeners;

use App\Actions\Pos\PushOrderToPos;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends an order to the POS as soon as it is confirmed. Runs on the queue and
 * is retried, so a POS outage never blocks the customer or the kitchen.
 */
class SyncConfirmedOrderToPos implements ShouldQueue
{
    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 300];

    public function __construct(private readonly PushOrderToPos $push) {}

    public function shouldQueue(OrderStatusChanged $event): bool
    {
        return $event->to === OrderStatus::Confirmed;
    }

    public function handle(OrderStatusChanged $event): void
    {
        $this->push->handle($event->order);
    }
}
