<?php

namespace App\Actions\Delivery;

use App\Actions\Orders\TransitionOrder;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Delivery;
use App\Models\User;

/**
 * The rider collected the order from the counter and is on the way.
 */
class MarkPickedUp
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @throws OrderException
     */
    public function handle(Delivery $delivery, ?User $by = null): Delivery
    {
        if (! $delivery->rider_id) {
            throw new OrderException('Assign a rider before dispatching this order.');
        }

        $order = $this->transition->handle($delivery->loadMissing('order')->order, OrderStatus::OutForDelivery, $by, 'Rider dispatched');

        $delivery->update(['status' => DeliveryStatus::PickedUp, 'picked_up_at' => now()]);

        return $delivery->setRelation('order', $order);
    }
}
