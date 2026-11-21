<?php

namespace App\Services\Payments;

use App\Data\PaymentResult;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Billplz (FPX online banking and cards, Malaysia). API v3, HTTP Basic auth
 * with the API secret key. Amounts are in sen.
 *
 * The bill is checked with GET /bills/{id} on every return and callback, so
 * the X Signature key is not needed.
 */
class BillplzGateway extends HostedGateway
{
    public function name(): string
    {
        return 'billplz';
    }

    public function label(): string
    {
        return 'Billplz';
    }

    public function configured(): bool
    {
        return $this->credentials->has('payments.billplz.api_key') && $this->credentials->has('payments.billplz.collection_id');
    }

    public function checkoutUrl(Payment $payment): string
    {
        $payment->loadMissing('order');
        $phone = preg_replace('/\D/', '', (string) $payment->order->customer_phone);

        $bill = $this->call($this->client(), fn (PendingRequest $http) => $http->post('bills', array_filter([
            'collection_id' => $this->credentials->get('payments.billplz.collection_id'),
            'email' => $payment->order->customer_email ?: ($phone ? null : $this->payerEmail($payment)),
            'mobile' => $phone ?: null,
            'name' => $this->payerName($payment),
            'amount' => $payment->amount,
            'callback_url' => $this->notifyUrl($payment),
            'redirect_url' => $this->returnUrl($payment),
            'description' => mb_substr($this->description($payment), 0, 200),
            'reference_1_label' => 'Order',
            'reference_1' => $payment->order->order_number,
        ], fn ($value) => $value !== null && $value !== '')))->json();

        if (empty($bill['id']) || empty($bill['url'])) {
            throw new PaymentException('Billplz did not return a payment page.');
        }

        $payment->update(['gateway_reference' => $bill['id']]);

        return $bill['url'];
    }

    public function verify(Payment $payment, ?Request $request = null): PaymentResult
    {
        if (! $payment->gateway_reference) {
            return PaymentResult::pending();
        }

        $bill = $this->call($this->client(), fn (PendingRequest $http) => $http->get('bills/'.$payment->gateway_reference))->json();

        if (($bill['paid'] ?? false) === true && (int) ($bill['paid_amount'] ?? $bill['amount'] ?? 0) >= $payment->amount) {
            return PaymentResult::paid((string) $bill['id'], ['state' => $bill['state'] ?? null, 'paid_at' => $bill['paid_at'] ?? null]);
        }

        // Back from the bank without paying, or the bill was deleted.
        $cancelled = $request && in_array((string) $request->input('billplz.paid'), ['false', '0'], true);

        return ($bill['state'] ?? null) === 'deleted' || $cancelled
            ? PaymentResult::failed(['state' => $bill['state'] ?? null])
            : PaymentResult::pending(['state' => $bill['state'] ?? null]);
    }

    public function test(): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Enter the API secret key and collection ID first.');
        }

        $collection = $this->call($this->client(), fn (PendingRequest $http) => $http->get('collections/'.$this->credentials->get('payments.billplz.collection_id')))->json();

        return 'Connected to Billplz'.($this->sandbox() ? ' (sandbox)' : '').'. Collection: '.($collection['title'] ?? $collection['id'] ?? '?').'.';
    }

    private function client(): PendingRequest
    {
        $base = $this->sandbox() ? 'https://www.billplz-sandbox.com/api/v3/' : 'https://www.billplz.com/api/v3/';

        return Http::baseUrl($base)->withBasicAuth((string) $this->credentials->get('payments.billplz.api_key'), '')->acceptJson()->asForm();
    }

    private function sandbox(): bool
    {
        return $this->credentials->bool('payments.billplz.sandbox');
    }
}
