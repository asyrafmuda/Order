<?php

namespace App\Actions\Kitchen;

use App\Actions\Orders\TransitionOrder;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Events\OrderUpdated;
use App\Exceptions\OrderException;
use App\Models\OrderItem;
use App\Models\User;

/**
 * A station started or finished an item. The order follows automatically:
 * the first item on the grill moves it to Preparing, the last one done moves
 * it to Ready.
 */
class UpdateKitchenItem
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @throws OrderException
     */
    public function handle(OrderItem $item, KitchenItemStatus $status, ?User $by = null): OrderItem
    {
        $order = $item->loadMissing('order')->order;

        if (! in_array($order->status, OrderStatus::kitchenQueue(), true)) {
            throw new OrderException("Order {$order->order_number} is not in the kitchen queue.");
        }

        $item->update([
            'kitchen_status' => $status,
            'started_at' => match ($status) {
                KitchenItemStatus::Queued => null,
                default => $item->started_at ?? now(),
            },
            'done_at' => $status === KitchenItemStatus::Done ? now() : null,
        ]);

        $allDone = ! $order->items()->where('kitchen_status', '!=', KitchenItemStatus::Done)->exists();

        if ($allDone) {
            $this->transition->handle($order, OrderStatus::Ready, $by, 'All items done');
        } elseif ($status !== KitchenItemStatus::Queued && $order->status === OrderStatus::Confirmed) {
            $this->transition->handle($order, OrderStatus::Preparing, $by);
        } else {
            OrderUpdated::dispatch($order);
        }

        return $item->refresh();
    }
}
