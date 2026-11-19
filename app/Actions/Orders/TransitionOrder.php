<?php

namespace App\Actions\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;
use App\Support\KitchenLoad;
use Illuminate\Support\Facades\DB;

/**
 * The only place an order's status is changed. Enforces the state machine in
 * OrderStatus::allowedTransitions(), stamps timestamps, records history and
 * announces the change.
 */
class TransitionOrder
{
    public function __construct(private readonly KitchenLoad $kitchenLoad) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, OrderStatus $to, ?User $by = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if ($from === $to) {
                return $order;
            }

            if (! $from->canTransitionTo($to)) {
                throw OrderException::invalidTransition($order, $to);
            }

            $order->fill(['status' => $to, ...$this->timestampsFor($order, $to)])->save();

            $order->histories()->create([
                'status' => $to,
                'user_id' => $by?->id,
                'note' => $note,
            ]);

            $this->cascade($order, $to);

            OrderStatusChanged::dispatch($order, $from, $to);
            OrderUpdated::dispatch($order);

            return $order;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function timestampsFor(Order $order, OrderStatus $to): array
    {
        return match ($to) {
            OrderStatus::Confirmed => [
                'confirmed_at' => now(),
                'estimated_ready_at' => now()->addMinutes($this->kitchenLoad->estimatedPrepMinutes()),
            ],
            OrderStatus::Preparing => ['preparing_at' => now()],
            OrderStatus::Ready => ['ready_at' => now(), 'preparing_at' => $order->preparing_at ?? now()],
            OrderStatus::OutForDelivery => ['dispatched_at' => now()],
            OrderStatus::Completed => ['completed_at' => now()],
            OrderStatus::Cancelled => ['cancelled_at' => now()],
            default => [],
        };
    }

    /**
     * Keep kitchen items and the delivery record consistent with the order.
     */
    private function cascade(Order $order, OrderStatus $to): void
    {
        if ($to === OrderStatus::Ready) {
            $order->items()
                ->where('kitchen_status', '!=', KitchenItemStatus::Done)
                ->update(['kitchen_status' => KitchenItemStatus::Done, 'done_at' => now()]);
        }

        if ($to === OrderStatus::Cancelled) {
            $order->delivery()
                ->where('status', '!=', DeliveryStatus::Delivered)
                ->update(['status' => DeliveryStatus::Failed]);
        }
    }
}
