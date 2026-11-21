<?php

namespace App\Support\Licensing;

use App\LicenseServer\LicenseServerServiceProvider;
use App\Support\Installer\Installation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * This copy's license: the signed certificate from the license server, and
 * what it means for the address the site is being used on.
 */
class License
{
    private Certificate|false|null $certificate = false;

    /**
     * Whether licensing applies to this build at all. Off while no public key
     * is configured, and on the seller's own license server.
     */
    public function configured(): bool
    {
        return trim((string) config('licensing.public_key')) !== '' && ! $this->isLicenseServer();
    }

    public function isLicenseServer(): bool
    {
        return config('licensing.server.enabled') && class_exists(LicenseServerServiceProvider::class);
    }

    /**
     * Local and private addresses (development, a shop's own network) never
     * need a license.
     */
    public function isExempt(string $host): bool
    {
        $host = self::normalize($host);

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return true;
        }

        if (preg_match('/\.(test|local|localhost)$/', $host)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function status(string $host): LicenseStatus
    {
        if (! $this->configured() || $this->isExempt($host)) {
            return LicenseStatus::NotRequired;
        }

        $certificate = $this->certificate();

        if ($certificate === null) {
            return is_file($this->file()) ? LicenseStatus::Invalid : LicenseStatus::Missing;
        }

        if ($certificate->get('product') !== config('licensing.product')) {
            return LicenseStatus::Invalid;
        }

        if ($certificate->domain() !== self::normalize($host)) {
            return LicenseStatus::WrongDomain;
        }

        if ($certificate->isRevoked()) {
            return LicenseStatus::Revoked;
        }

        $checked = $certificate->checkedAt();

        return match (true) {
            $checked === null => LicenseStatus::Invalid,
            $checked->lt(now()->subDays((int) config('licensing.lock_after_days'))) => LicenseStatus::Expired,
            $checked->lt(now()->subDays((int) config('licensing.warn_after_days'))) => LicenseStatus::Warning,
            $checked->lt(now()->subDays((int) config('licensing.check_every_days'))) => LicenseStatus::Stale,
            default => LicenseStatus::Valid,
        };
    }

    public function certificate(): ?Certificate
    {
        if ($this->certificate !== false) {
            return $this->certificate;
        }

        $stored = is_file($this->file()) ? json_decode((string) file_get_contents($this->file()), true) : null;

        return $this->certificate = is_array($stored) && isset($stored['payload'], $stored['signature'])
            ? Certificate::verify((string) $stored['payload'], (string) $stored['signature'], (string) config('licensing.public_key'))
            : null;
    }

    /**
     * Activate a purchase code for this address.
     *
     * @throws RuntimeException with a message for the site owner
     */
    public function activate(string $code, string $host): Certificate
    {
        $response = $this->call('activate', ['code' => self::normalizeCode($code), 'domain' => self::normalize($host)]);

        return $this->store($response, $host);
    }

    /**
     * Ask the license server for a fresh certificate. A revocation is stored
     * like any other answer; no answer leaves the current certificate alone.
     *
     * @throws RuntimeException when the server cannot be reached or refuses
     */
    public function refresh(string $host): Certificate
    {
        $current = $this->certificate();

        if ($current === null) {
            throw new RuntimeException('There is no license to check yet. Enter your purchase code.');
        }

        $response = $this->call('check', ['code' => $current->get('code'), 'domain' => $current->domain()]);

        return $this->store($response, $current->domain());
    }

    /**
     * Refresh quietly, e.g. from the scheduler. Returns whether it worked.
     */
    public function refreshQuietly(?string $host = null): bool
    {
        $certificate = $this->certificate();

        if (! $this->configured() || $certificate === null) {
            return false;
        }

        try {
            $this->refresh($host ?? $certificate->domain());

            return true;
        } catch (Throwable $exception) {
            Log::warning('License check failed: '.$exception->getMessage());

            return false;
        }
    }

    /**
     * Free this address on the license server (before moving to another
     * domain) and forget the certificate here.
     *
     * @throws RuntimeException
     */
    public function release(): void
    {
        $current = $this->certificate();

        if ($current !== null) {
            $this->call('deactivate', ['code' => $current->get('code'), 'domain' => $current->domain()]);
        }

        @unlink($this->file());
        $this->certificate = null;
    }

    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $code) ?? '');
    }

    /**
     * Shown in the admin: the code with only the last group visible.
     */
    public static function mask(string $code): string
    {
        return preg_replace('/[A-Z0-9](?=.*-[A-Z0-9]+$)/', '•', $code) ?? $code;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function call(string $action, array $data): array
    {
        $server = rtrim((string) config('licensing.server_url'), '/');

        if ($server === '') {
            throw new RuntimeException('No license server is set in config/licensing.php.');
        }

        try {
            $response = Http::acceptJson()->timeout(15)->connectTimeout(8)->post("{$server}/api/license/{$action}", [
                ...$data,
                'product' => config('licensing.product'),
                'version' => app(Installation::class)->version(),
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach the license server. Check that this server can make outgoing HTTPS connections, then try again.');
        }

        if ($response->failed()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'The license server could not handle the request (HTTP '.$response->status().'). Try again later.'));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function store(array $response, string $host): Certificate
    {
        $certificate = Certificate::verify((string) ($response['payload'] ?? ''), (string) ($response['signature'] ?? ''), (string) config('licensing.public_key'));

        if ($certificate === null || $certificate->get('product') !== config('licensing.product') || $certificate->domain() !== self::normalize($host)) {
            throw new RuntimeException('The license server sent an answer that could not be verified.');
        }

        $file = $this->file();

        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }

        file_put_contents($file, json_encode($certificate->toArray(), JSON_PRETTY_PRINT)."\n", LOCK_EX);
        $this->certificate = $certificate;

        if ($certificate->isRevoked()) {
            throw new RuntimeException((string) ($certificate->get('message') ?: 'The seller cancelled this license.'));
        }

        return $certificate;
    }

    private function file(): string
    {
        return (string) config('licensing.file');
    }
}
