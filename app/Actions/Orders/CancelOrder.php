<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\GatewayManager;

/**
 * Cancels an order and refunds whatever was paid for it.
 */
class CancelOrder
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly GatewayManager $gateways,
    ) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, ?string $reason = null, ?User $by = null): Order
    {
        $order = $this->transition->handle($order, OrderStatus::Cancelled, $by, $reason);
        $order->update(['cancel_reason' => $reason]);

        if ($order->isPaid()) {
            $this->refund($order);
        }

        return $order;
    }

    private function refund(Order $order): void
    {
        $order->payments()->where('status', PaymentStatus::Paid)->get()->each(function (Payment $payment) {
            // Counter (cash) payments are handed back by the cashier.
            $refunded = array_key_exists($payment->gateway, (array) config('payment.drivers'))
                ? $this->gateways->driver($payment->gateway)->refund($payment)
                : true;

            if ($refunded) {
                $payment->update(['status' => PaymentStatus::Refunded, 'refunded_at' => now()]);
            }
        });

        if (! $order->payments()->where('status', PaymentStatus::Paid)->exists()) {
            $order->update(['payment_status' => PaymentStatus::Refunded]);
        }
    }
}
