<?php

namespace App\Contracts;

use App\Data\PaymentResult;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * An online payment provider (FPX / card / e-wallet).
 *
 * The customer is sent to the gateway's own page and comes back to
 * /payments/{gateway}/return; the gateway also calls /payments/{gateway}/notify.
 * Neither is trusted: verify() asks the gateway itself whether the bill was
 * paid, so a forged return URL cannot mark an order as paid.
 *
 * Register a new driver under config/payment.php "drivers".
 */
interface PaymentGateway
{
    /**
     * Driver name stored on payments, e.g. "billplz".
     */
    public function name(): string;

    /**
     * Name shown to the shop owner, e.g. "Billplz".
     */
    public function label(): string;

    /**
     * Whether the credentials it needs are filled in.
     */
    public function configured(): bool;

    /**
     * Whether it moves real money (false for the simulated gateway).
     */
    public function live(): bool;

    /**
     * Create the bill / checkout session at the gateway, store its id on the
     * payment (gateway_reference) and return the URL the customer pays on.
     *
     * @throws PaymentException when the gateway refuses or cannot be reached
     */
    public function checkoutUrl(Payment $payment): string;

    /**
     * The gateway's answer on whether the payment went through. The request
     * (a return or notify call) is only a hint; the gateway decides.
     *
     * @throws PaymentException when the gateway cannot be asked, or the request is forged
     */
    public function verify(Payment $payment, ?Request $request = null): PaymentResult;

    /**
     * Refund a paid payment in full. False when the gateway has no refund API
     * or refused: the shop then refunds it from the gateway's dashboard.
     */
    public function refund(Payment $payment): bool;

    /**
     * Check the saved credentials with a harmless call. Returns what the
     * gateway said about the account.
     *
     * @throws PaymentException with a message for the shop owner
     */
    public function test(): string;
}
