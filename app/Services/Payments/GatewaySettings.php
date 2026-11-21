<?php

namespace App\Services\Payments;

use App\Support\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The payment gateway credentials saved in Admin → Payment Gateway. Secret
 * ones are stored encrypted with the app key and never sent to the browser.
 */
class GatewaySettings
{
    /** Settings keys holding secrets. */
    public const SECRETS = [
        'payments.billplz.api_key',
        'payments.bayarcash.token',
        'payments.bayarcash.secret_key',
        'payments.stripe.secret_key',
    ];

    public function __construct(private readonly Settings $settings) {}

    public function get(string $key): ?string
    {
        $value = $this->settings->global($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (! in_array($key, self::SECRETS, true)) {
            return (string) $value;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Saved under another app key: as good as not set.
            return null;
        }
    }

    public function bool(string $key): bool
    {
        return (bool) $this->settings->global($key, false);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Save values. An empty secret means "keep the one already saved".
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        foreach (self::SECRETS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if ($values[$key] === null || trim((string) $values[$key]) === '') {
                unset($values[$key]);
            } else {
                $values[$key] = Crypt::encryptString(trim((string) $values[$key]));
            }
        }

        $this->settings->put($values);
    }
}
