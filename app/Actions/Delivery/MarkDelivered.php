<?php

namespace App\Actions\Delivery;

use App\Actions\Orders\TransitionOrder;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Delivery;
use App\Models\User;

class MarkDelivered
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @throws OrderException
     */
    public function handle(Delivery $delivery, ?User $by = null): Delivery
    {
        // A Ready order can be completed directly at the counter, so make
        // sure a delivery was actually collected before it is marked delivered.
        if ($delivery->status !== DeliveryStatus::PickedUp) {
            throw new OrderException('Collect the order from the counter before marking it delivered.');
        }

        $order = $this->transition->handle($delivery->loadMissing('order')->order, OrderStatus::Completed, $by, 'Delivered to customer');

        $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);

        return $delivery->setRelation('order', $order);
    }
}
