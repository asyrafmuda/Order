<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;

/**
 * Hands a ready pickup order over to the customer.
 */
class CompleteOrder
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, ?User $by = null): Order
    {
        if (! $order->isPaid()) {
            throw OrderException::unpaid($order);
        }

        if ($order->isDelivery()) {
            throw new OrderException('Delivery orders are completed by the rider.');
        }

        return $this->transition->handle($order, OrderStatus::Completed, $by, 'Collected at counter');
    }
}
