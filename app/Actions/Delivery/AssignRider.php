<?php

namespace App\Actions\Delivery;

use App\Enums\DeliveryStatus;
use App\Events\OrderUpdated;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Rider;

class AssignRider
{
    /**
     * @throws OrderException
     */
    public function handle(Order $order, ?Rider $rider): Order
    {
        $delivery = $order->loadMissing('delivery')->delivery;
        $rider?->loadMissing('user');

        if (! $delivery || $order->status->isFinal()) {
            throw new OrderException("Order {$order->order_number} has no open delivery.");
        }

        if (in_array($delivery->status, [DeliveryStatus::PickedUp, DeliveryStatus::Delivered], true)) {
            throw new OrderException('The rider has already collected this order.');
        }

        if ($rider && ! $rider->user->is_active) {
            throw new OrderException('That rider account is inactive.');
        }

        $delivery->update([
            'rider_id' => $rider?->id,
            'status' => $rider ? DeliveryStatus::Assigned : DeliveryStatus::Pending,
            'assigned_at' => $rider ? now() : null,
        ]);

        OrderUpdated::dispatch($order);

        return $order;
    }
}
