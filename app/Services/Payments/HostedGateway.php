<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Shared plumbing for gateways that host their own payment page and answer
 * status questions over an HTTPS API.
 */
abstract class HostedGateway implements PaymentGateway
{
    public function __construct(protected readonly GatewaySettings $credentials) {}

    public function live(): bool
    {
        return true;
    }

    public function refund(Payment $payment): bool
    {
        return false;
    }

    /**
     * Where the customer comes back to after paying (or giving up).
     */
    protected function returnUrl(Payment $payment): string
    {
        return route('payments.return', ['gateway' => $this->name(), 'payment' => $payment->reference]);
    }

    /**
     * Where the gateway reports the result server to server.
     */
    protected function notifyUrl(Payment $payment): string
    {
        return route('payments.notify', ['gateway' => $this->name(), 'payment' => $payment->reference]);
    }

    protected function description(Payment $payment): string
    {
        $shop = (string) app(Settings::class)->global('shop.name', config('app.name'));

        return mb_substr("Order {$payment->order->order_number} · {$shop}", 0, 120);
    }

    /**
     * Some gateways insist on an email. Guests often give only a phone number,
     * so a placeholder that can never receive mail stands in.
     */
    protected function payerEmail(Payment $payment): string
    {
        return $payment->order->customer_email ?: strtolower($payment->reference).'@guest.invalid';
    }

    protected function payerName(Payment $payment): string
    {
        return mb_substr($payment->order->customer_name ?: 'Customer', 0, 120);
    }

    protected static function ringgit(int $sen): string
    {
        return number_format($sen / 100, 2, '.', '');
    }

    protected static function sen(mixed $ringgit): int
    {
        return (int) round(((float) $ringgit) * 100);
    }

    /**
     * Send a request and turn every failure into a PaymentException with a
     * message the shop owner (or the customer) can act on.
     *
     * @param  callable(PendingRequest): Response  $send
     */
    protected function call(PendingRequest $client, callable $send): Response
    {
        try {
            $response = $send($client->timeout(20)->connectTimeout(8));
        } catch (ConnectionException) {
            throw new PaymentException("{$this->label()} could not be reached. Please try again in a moment.");
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new PaymentException("{$this->label()} did not accept the credentials. Check them in Admin → Payment Gateway.");
        }

        if ($response->failed()) {
            throw new PaymentException("{$this->label()} refused the request: ".$this->errorMessage($response));
        }

        return $response;
    }

    protected function errorMessage(Response $response): string
    {
        $json = $response->json();
        $message = is_array($json) ? ($json['message'] ?? $json['error']['message'] ?? $json['error'] ?? null) : null;

        if (is_array($message)) {
            $message = implode(' ', array_map(fn ($part) => is_array($part) ? implode(' ', $part) : (string) $part, $message));
        }

        if (is_array($json) && isset($json['errors']) && is_array($json['errors'])) {
            $message = trim(($message ? $message.' ' : '').implode(' ', array_map(fn ($part) => is_array($part) ? implode(' ', $part) : (string) $part, $json['errors'])));
        }

        return $message ? (string) $message : 'HTTP '.$response->status();
    }
}
