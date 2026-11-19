<?php

namespace App\Listeners;

use App\Actions\Pos\PushOrderToPos;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\PosException;
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

    /**
     * @throws PosException so the queue retries with backoff
     */
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $this->push->handle($event->order);
        } catch (PosException $exception) {
            // On the "sync" queue this runs inside the customer's request,
            // where a POS outage must not turn into an error page. The
            // failure is already logged on the order and can be retried
            // from Admin → Orders.
            if (config('queue.default') === 'sync') {
                report($exception);

                return;
            }

            throw $exception;
        }
    }
}
