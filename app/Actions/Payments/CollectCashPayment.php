<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderUpdated;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The cashier took payment at the till: cash for a pay-at-counter order, or
 * cash / card terminal / QR for an order keyed in at the counter.
 */
class CollectCashPayment
{
    /**
     * @throws OrderException
     */
    public function handle(Order $order, User $cashier): Order
    {
        if ($order->isPaid()) {
            return $order;
        }

        $payableAtCounter = $order->payment_method === PaymentMethod::Cash || $order->isCounterSale();

        if ($order->status->isFinal() || ! $payableAtCounter) {
            throw new OrderException("Order {$order->order_number} cannot be paid at the counter.");
        }

        $order->payments()->create([
            'reference' => 'TILL-'.strtoupper(Str::random(12)),
            'gateway' => 'counter',
            'method' => $order->payment_method,
            'status' => PaymentStatus::Paid,
            'amount' => $order->total,
            'payload' => ['collected_by' => $cashier->id],
            'paid_at' => now(),
        ]);

        $order->update(['payment_status' => PaymentStatus::Paid]);

        OrderUpdated::dispatch($order);

        return $order;
    }
}
