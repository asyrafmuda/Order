<?php

namespace App\Actions\Payments;

use App\Actions\Orders\TransitionOrder;
use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Records a successful payment and releases the order to the kitchen.
 * Safe to call more than once for the same payment (gateways retry callbacks).
 */
class ConfirmPayment
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Payment $payment, ?string $transactionId = null, array $payload = []): Order
    {
        $alreadyPaid = DB::transaction(function () use ($payment, $transactionId, $payload) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending && $payment->status !== PaymentStatus::Failed) {
                return true;
            }

            $payment->update([
                'status' => PaymentStatus::Paid,
                'gateway_transaction_id' => $transactionId,
                'payload' => $payload,
                'paid_at' => now(),
            ]);

            $payment->order()->update(['payment_status' => PaymentStatus::Paid]);

            return false;
        });

        $order = $payment->order()->firstOrFail();

        if ($alreadyPaid) {
            return $order;
        }

        // Paid after the order was cancelled (e.g. it timed out): give the money back.
        if ($order->status === OrderStatus::Cancelled) {
            if ($this->gateway->refund($payment)) {
                $payment->update(['status' => PaymentStatus::Refunded, 'refunded_at' => now()]);
                $order->update(['payment_status' => PaymentStatus::Refunded]);
            }

            return $order;
        }

        if ($order->status === OrderStatus::PendingPayment) {
            return $this->transition->handle(
                $order,
                OrderStatus::Confirmed,
                note: 'Payment verified via '.$payment->method->label(),
            );
        }

        return $order;
    }
}
