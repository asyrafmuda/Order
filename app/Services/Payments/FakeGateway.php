<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentResult;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * A stand-in gateway for development and demos. It sends the customer to a
 * simulated bank page hosted by this application, which returns through the
 * same route a real gateway uses, signed with an HMAC. It approves anything,
 * so it never takes payments on a live site (see GatewayManager::ready()).
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Demo (simulated bank page)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function live(): bool
    {
        return false;
    }

    public function checkoutUrl(Payment $payment): string
    {
        $payment->update(['gateway_reference' => 'FAKE-'.$payment->reference]);

        return URL::temporarySignedRoute('payments.fake.show', now()->addMinutes(30), ['payment' => $payment]);
    }

    /**
     * The return URL the simulated bank page sends the customer to.
     */
    public function callbackUrl(Payment $payment, bool $approved): string
    {
        $params = [
            'payment' => $payment->reference,
            'status' => $approved ? 'success' : 'failed',
            'transaction_id' => 'FAKE-'.strtoupper(substr(sha1($payment->reference), 0, 10)),
        ];

        return route('payments.return', ['gateway' => $this->name(), ...$params, 'signature' => $this->sign($params)]);
    }

    /**
     * There is no bank to ask, so the signed return URL is the answer.
     */
    public function verify(Payment $payment, ?Request $request = null): PaymentResult
    {
        if ($request === null || ! $request->has('signature')) {
            return PaymentResult::pending();
        }

        $params = [
            'payment' => (string) $request->query('payment'),
            'status' => (string) $request->query('status'),
            'transaction_id' => (string) $request->query('transaction_id'),
        ];

        if ($params['payment'] !== $payment->reference || ! hash_equals($this->sign($params), (string) $request->query('signature'))) {
            throw new PaymentException('Payment callback signature mismatch.');
        }

        return $params['status'] === 'success'
            ? PaymentResult::paid($params['transaction_id'], $params)
            : PaymentResult::failed($params);
    }

    public function refund(Payment $payment): bool
    {
        return true;
    }

    public function test(): string
    {
        return 'The demo gateway approves every payment with one click. It only works on test and demo sites, never on a live one.';
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
