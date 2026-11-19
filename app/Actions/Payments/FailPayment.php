<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Events\OrderUpdated;
use App\Models\Payment;

/**
 * Records a declined / abandoned payment attempt. The order stays open so the
 * customer can try again.
 */
class FailPayment
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Payment $payment, array $payload = []): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        $payment->update(['status' => PaymentStatus::Failed, 'payload' => $payload]);

        $order = $payment->order;

        if (! $order->isPaid()) {
            $order->update(['payment_status' => PaymentStatus::Failed]);
            OrderUpdated::dispatch($order);
        }
    }
}
