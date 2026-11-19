<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCallback;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * A stand-in gateway for development and demos. It sends the customer to a
 * simulated bank page hosted by this application, then returns through the
 * same callback route a real gateway would use, with an HMAC signature.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function checkoutUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('payments.fake.show', now()->addMinutes(30), ['payment' => $payment]);
    }

    /**
     * The callback URL the simulated bank page redirects to.
     */
    public function callbackUrl(Payment $payment, bool $approved): string
    {
        $params = [
            'gateway' => $this->name(),
            'reference' => $payment->reference,
            'status' => $approved ? 'success' : 'failed',
            'transaction_id' => 'FAKE-'.strtoupper(substr(sha1($payment->reference), 0, 10)),
        ];

        return route('payments.callback', [...$params, 'signature' => $this->sign($params)]);
    }

    public function parseCallback(Request $request): PaymentCallback
    {
        $params = [
            'gateway' => $this->name(),
            'reference' => (string) $request->query('reference'),
            'status' => (string) $request->query('status'),
            'transaction_id' => (string) $request->query('transaction_id'),
        ];

        if (! hash_equals($this->sign($params), (string) $request->query('signature'))) {
            throw new PaymentException('Payment callback signature mismatch.');
        }

        return new PaymentCallback(
            reference: $params['reference'],
            successful: $params['status'] === 'success',
            transactionId: $params['transaction_id'],
            payload: $params,
        );
    }

    public function refund(Payment $payment): bool
    {
        return true;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sign(array $params): string
    {
        ksort($params);

        return hash_hmac('sha256', http_build_query($params), (string) config('app.key'));
    }
}
