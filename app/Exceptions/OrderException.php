<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use RuntimeException;

/**
 * A business rule prevented an order operation. The message is safe to show
 * to the person who triggered it.
 */
class OrderException extends RuntimeException
{
    public static function invalidTransition(Order $order, OrderStatus $to): self
    {
        return new self(sprintf(
            'Order %s is %s and cannot be moved to %s.',
            $order->order_number,
            strtolower($order->status->label()),
            strtolower($to->label()),
        ));
    }

    public static function emptyCart(): self
    {
        return new self('Your ticket is empty. Add something tasty first.');
    }

    public static function shopClosed(string $message): self
    {
        return new self($message);
    }

    public static function unpaid(Order $order): self
    {
        return new self("Order {$order->order_number} has not been paid yet.");
    }
}
