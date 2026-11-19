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
        $order = $this->transition->handle($delivery->loadMissing('order')->order, OrderStatus::Completed, $by, 'Delivered to customer');

        $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);

        return $delivery->setRelation('order', $order);
    }
}
