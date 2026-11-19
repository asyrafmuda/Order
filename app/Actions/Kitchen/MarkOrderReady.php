<?php

namespace App\Actions\Kitchen;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;

/**
 * The "Mark Order Ready!" button: finishes every remaining item at once.
 */
class MarkOrderReady
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, ?User $by = null): Order
    {
        return $this->transition->handle($order, OrderStatus::Ready, $by, 'Marked ready by kitchen');
    }
}
