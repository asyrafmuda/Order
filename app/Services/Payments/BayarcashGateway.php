<?php

namespace App\Services\Payments;

use App\Data\PaymentResult;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Bayarcash (FPX, DuitNow, cards, e-wallets, Malaysia). API v3 with a Bearer
 * token; amounts are ringgit with two decimals. The payment intent's
 * order_number is our payment reference, and the transactions with that
 * number are checked on every return and callback.
 */
class BayarcashGateway extends HostedGateway
{
    /** Bayarcash payment_channel for each way of paying offered at checkout. */
    private const CHANNELS = [
        'fpx' => 1,      // FPX online banking
        'ewallet' => 6,  // DuitNow QR: every Malaysian e-wallet and banking app
        'card' => 12,    // Credit / debit card
    ];

    private const SUCCESS = 3;

    /** Failed, cancelled, abandoned. */
    private const UNSUCCESSFUL = [2, 4, 5];

    public function name(): string
    {
        return 'bayarcash';
    }

    public function label(): string
    {
        return 'Bayarcash';
    }

    public function configured(): bool
    {
        return $this->credentials->has('payments.bayarcash.token') && $this->credentials->has('payments.bayarcash.portal_key');
    }

    public function checkoutUrl(Payment $payment): string
    {
        $payment->loadMissing('order');

        $data = [
            'portal_key' => $this->credentials->get('payments.bayarcash.portal_key'),
            'payment_channel' => self::CHANNELS[$payment->method->value] ?? self::CHANNELS[PaymentMethod::Fpx->value],
            'order_number' => $payment->reference,
            'amount' => self::ringgit($payment->amount),
            'payer_name' => $this->payerName($payment),
            'payer_email' => $this->payerEmail($payment),
            'payer_telephone_number' => preg_replace('/\D/', '', (string) $payment->order->customer_phone) ?: null,
            'return_url' => $this->returnUrl($payment),
            'callback_url' => $this->notifyUrl($payment),
        ];

        if ($secret = $this->credentials->get('payments.bayarcash.secret_key')) {
            $data['checksum'] = self::checksum([
                'payment_channel' => $data['payment_channel'],
                'order_number' => $data['order_number'],
                'amount' => $data['amount'],
                'payer_name' => $data['payer_name'],
                'payer_email' => $data['payer_email'],
            ], $secret);
        }

        $intent = $this->call($this->client(), fn (PendingRequest $http) => $http->post('payment-intents', array_filter($data, fn ($value) => $value !== null)))->json();

        if (empty($intent['url'])) {
            throw new PaymentException('Bayarcash did not return a payment page.');
        }

        $payment->update(['gateway_reference' => $intent['id'] ?? null]);

        return $intent['url'];
    }

    public function verify(Payment $payment, ?Request $request = null): PaymentResult
    {
        $response = $this->call($this->client(), fn (PendingRequest $http) => $http->get('transactions', ['order_number' => $payment->reference]))->json();
        $transactions = array_values(array_filter(
            (array) ($response['data'] ?? []),
            fn ($transaction) => is_array($transaction) && ($transaction['order_number'] ?? null) === $payment->reference,
        ));

        foreach ($transactions as $transaction) {
            if ((int) ($transaction['status'] ?? -1) === self::SUCCESS && self::sen($transaction['amount'] ?? 0) >= $payment->amount) {
                return PaymentResult::paid((string) ($transaction['id'] ?? ''), [
                    'exchange_reference_number' => $transaction['exchange_reference_number'] ?? null,
                    'payer_bank_name' => $transaction['payer_bank_name'] ?? null,
                ]);
            }
        }

        $last = end($transactions) ?: null;

        return $last && in_array((int) ($last['status'] ?? -1), self::UNSUCCESSFUL, true)
            ? PaymentResult::failed(['status_description' => $last['status_description'] ?? null])
            : PaymentResult::pending();
    }

    public function test(): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Enter the API token and portal key first.');
        }

        $portalKey = (string) $this->credentials->get('payments.bayarcash.portal_key');
        $portals = (array) ($this->call($this->client(), fn (PendingRequest $http) => $http->get('portals', ['portal_key' => $portalKey]))->json('data') ?? []);
        $portal = collect($portals)->first(fn ($item) => is_array($item) && ($item['portal_key'] ?? null) === $portalKey);

        if (! $portal) {
            throw new PaymentException('The token works, but no portal with that portal key was found on this Bayarcash account.');
        }

        return 'Connected to Bayarcash'.($this->sandbox() ? ' (sandbox)' : '').'. Portal: '.($portal['portal_name'] ?? $portal['name'] ?? $portalKey).'.';
    }

    /**
     * Bayarcash checksum: values sorted by key, joined with "|", HMAC-SHA256
     * with the API secret key (as in their official SDK).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function checksum(array $payload, string $secret): string
    {
        ksort($payload);

        return hash_hmac('sha256', implode('|', $payload), $secret);
    }

    private function client(): PendingRequest
    {
        $base = $this->sandbox() ? 'https://api.console.bayarcash-sandbox.com/v3/' : 'https://api.console.bayar.cash/v3/';

        return Http::baseUrl($base)->withToken((string) $this->credentials->get('payments.bayarcash.token'))->acceptJson()->asForm();
    }

    private function sandbox(): bool
    {
        return $this->credentials->bool('payments.bayarcash.sandbox');
    }
}
