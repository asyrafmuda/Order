<?php

namespace App\Actions\Payments;

use App\Data\PaymentResult;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\Request;

/**
 * Asks a payment's own gateway how it went and records the answer: paid
 * releases the order, failed lets the customer try again, pending waits.
 */
class ReconcilePayment
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ConfirmPayment $confirm,
        private readonly FailPayment $fail,
    ) {}

    /**
     * @throws PaymentException
     */
    public function handle(Payment $payment, ?Request $request = null): PaymentResult
    {
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
            return PaymentResult::paid($payment->gateway_transaction_id);
        }

        $result = $this->gateways->driver($payment->gateway)->verify($payment, $request);

        if ($result->isPaid()) {
            $this->confirm->handle($payment, $result->transactionId, $result->payload);
        } elseif ($result->isFailed() && $payment->status === PaymentStatus::Pending) {
            $this->fail->handle($payment, $result->payload);
        }

        return $result;
    }
}
