<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;
use Illuminate\Support\Str;

/**
 * Opens a payment attempt for an unpaid online order and returns the URL the
 * customer should be redirected to.
 */
class StartPayment
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order): string
    {
        if ($order->status !== OrderStatus::PendingPayment || ! $order->payment_method->isOnline()) {
            throw new OrderException("Order {$order->order_number} is not awaiting online payment.");
        }

        $gateway = $this->gateways->ready() ? $this->gateways->active() : null;

        if (! $gateway) {
            throw new OrderException('Online payment is not available right now. Please choose to pay at the counter.');
        }

        /** @var Payment $payment */
        $payment = $order->payments()->create([
            'reference' => 'PAY-'.strtoupper(Str::random(12)),
            'gateway' => $gateway->name(),
            'method' => $order->payment_method,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
        ]);

        $order->update(['payment_status' => PaymentStatus::Pending]);

        try {
            return $gateway->checkoutUrl($payment);
        } catch (PaymentException $exception) {
            report($exception);
            $payment->update(['status' => PaymentStatus::Failed]);
            $order->update(['payment_status' => PaymentStatus::Failed]);

            throw new OrderException('The payment page could not be opened: '.$exception->getMessage().' Your order is saved — try paying again from the order page.');
        }
    }
}
