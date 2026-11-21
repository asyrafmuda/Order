<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Support\Settings;
use InvalidArgumentException;

/**
 * Which payment gateway takes online payments, as chosen in Admin → Payment
 * Gateway. Payments made earlier keep their own gateway (for checks and
 * refunds) even after the owner switches to another one.
 */
class GatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $drivers = [];

    public function __construct(private readonly Settings $settings) {}

    /**
     * The chosen gateway's name, or "none".
     */
    public function activeName(): string
    {
        $name = (string) $this->settings->global('payments.gateway', config('payment.default'));

        // The demo gateway approves payments without taking money: on a live site it counts as off.
        if ($name === 'fake' && app()->isProduction() && ! config('payment.fake_in_production')) {
            return 'none';
        }

        return array_key_exists($name, (array) config('payment.drivers')) ? $name : 'none';
    }

    public function active(): ?PaymentGateway
    {
        $name = $this->activeName();

        return $name === 'none' ? null : $this->driver($name);
    }

    public function driver(string $name): PaymentGateway
    {
        $class = config("payment.drivers.{$name}");

        if (! $class) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not defined in config/payment.php.");
        }

        return $this->drivers[$name] ??= app($class);
    }

    /**
     * Whether customers can pay online right now: a gateway is chosen, has its
     * credentials, and takes real money — or is the demo gateway where that is
     * allowed (local, tests, or PAYMENT_FAKE_IN_PRODUCTION).
     */
    public function ready(): bool
    {
        $gateway = $this->active();

        if (! $gateway || ! $gateway->configured()) {
            return false;
        }

        return $gateway->live() || ! app()->isProduction() || config('payment.fake_in_production');
    }
}
