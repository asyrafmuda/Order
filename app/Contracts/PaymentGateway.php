<?php

namespace App\Contracts;

use App\Data\PaymentCallback;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * An online payment provider (FPX / card / e-wallet).
 *
 * To add a real gateway (ToyyibPay, Billplz, Stripe, ...) implement this
 * interface and register the class under config/payment.php "drivers".
 */
interface PaymentGateway
{
    /**
     * Driver name stored on payments, e.g. "fake" or "toyyibpay".
     */
    public function name(): string;

    /**
     * Create the bill at the gateway and return the URL the customer is sent
     * to in order to pay.
     */
    public function checkoutUrl(Payment $payment): string;

    /**
     * Verify and normalise a return / webhook request from the gateway.
     *
     * @throws PaymentException when the request is not authentic
     */
    public function parseCallback(Request $request): PaymentCallback;

    /**
     * Refund a paid payment in full. Returns false when the gateway refused.
     */
    public function refund(Payment $payment): bool;
}
