<?php

namespace App\Services\Payments;

use App\Data\PaymentResult;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (cards, and FPX / GrabPay where switched on in the Stripe
 * dashboard). The customer pays on Stripe's page; the Checkout Session is
 * fetched on every return and webhook to see whether it was paid.
 */
class StripeGateway extends HostedGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function configured(): bool
    {
        return $this->credentials->has('payments.stripe.secret_key');
    }

    public function checkoutUrl(Payment $payment): string
    {
        $payment->loadMissing('order');
        $return = $this->returnUrl($payment);

        $session = $this->call($this->client(), fn (PendingRequest $http) => $http->post('checkout/sessions', array_filter([
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            // Stripe fills in {CHECKOUT_SESSION_ID}; it must stay unencoded.
            'success_url' => $return.'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $return.'&cancelled=1',
            'customer_email' => $payment->order->customer_email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'myr',
                    'unit_amount' => $payment->amount,
                    'product_data' => ['name' => $this->description($payment)],
                ],
            ]],
            'metadata' => ['payment_reference' => $payment->reference, 'order_number' => $payment->order->order_number],
            'payment_intent_data' => ['metadata' => ['payment_reference' => $payment->reference]],
        ], fn ($value) => $value !== null)))->json();

        if (empty($session['id']) || empty($session['url'])) {
            throw new PaymentException('Stripe did not return a payment page.');
        }

        $payment->update(['gateway_reference' => $session['id']]);

        return $session['url'];
    }

    public function verify(Payment $payment, ?Request $request = null): PaymentResult
    {
        if (! $payment->gateway_reference) {
            return PaymentResult::pending();
        }

        $session = $this->call($this->client(), fn (PendingRequest $http) => $http->get('checkout/sessions/'.$payment->gateway_reference))->json();

        $paid = in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true)
            && ($session['client_reference_id'] ?? null) === $payment->reference
            && strtolower((string) ($session['currency'] ?? '')) === 'myr'
            && (int) ($session['amount_total'] ?? 0) >= $payment->amount;

        if ($paid) {
            return PaymentResult::paid((string) ($session['payment_intent'] ?? $session['id']), ['session' => $session['id']]);
        }

        $cancelled = $request && $request->boolean('cancelled');

        return ($session['status'] ?? null) === 'expired' || $cancelled
            ? PaymentResult::failed(['status' => $session['status'] ?? null])
            : PaymentResult::pending(['status' => $session['status'] ?? null]);
    }

    public function refund(Payment $payment): bool
    {
        if (! $payment->gateway_transaction_id || ! str_starts_with($payment->gateway_transaction_id, 'pi_')) {
            return false;
        }

        try {
            $refund = $this->call($this->client(), fn (PendingRequest $http) => $http->post('refunds', ['payment_intent' => $payment->gateway_transaction_id]))->json();
        } catch (PaymentException $exception) {
            report($exception);

            return false;
        }

        return in_array($refund['status'] ?? null, ['succeeded', 'pending'], true);
    }

    public function test(): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Enter the secret key first.');
        }

        $balance = $this->call($this->client(), fn (PendingRequest $http) => $http->get('balance'))->json();

        return 'Connected to Stripe ('.(($balance['livemode'] ?? false) ? 'live mode' : 'test mode — no real money').').';
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.stripe.com/v1/')->withToken((string) $this->credentials->get('payments.stripe.secret_key'))->acceptJson()->asForm();
    }
}
