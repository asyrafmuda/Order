<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Opens a payment attempt for an unpaid online order and returns the URL the
 * customer should be redirected to.
 */
class StartPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order): string
    {
        if ($order->status !== OrderStatus::PendingPayment || ! $order->payment_method->isOnline()) {
            throw new OrderException("Order {$order->order_number} is not awaiting online payment.");
        }

        /** @var Payment $payment */
        $payment = $order->payments()->create([
            'reference' => 'PAY-'.strtoupper(Str::random(12)),
            'gateway' => $this->gateway->name(),
            'method' => $order->payment_method,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
        ]);

        $order->update(['payment_status' => PaymentStatus::Pending]);

        return $this->gateway->checkoutUrl($payment);
    }
}
