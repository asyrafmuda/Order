<?php

namespace App\LicenseServer;

use App\LicenseServer\Models\Activation;
use App\LicenseServer\Models\License;
use App\Support\Licensing\Certificate;
use RuntimeException;

/**
 * Signs the certificates sent to customers' sites with the private key in
 * LICENSE_SIGNING_KEY. Whoever holds that key can license any site, so it
 * lives only in the license server's .env (and in a safe backup).
 */
class Signer
{
    /**
     * @return array{payload: string, signature: string}
     */
    public function active(License $license, Activation $activation): array
    {
        return $this->sign([
            'product' => $license->product,
            'code' => $license->code,
            'domain' => $activation->domain,
            'customer' => $license->customer_name,
            'status' => 'active',
            'issued_at' => $activation->activated_at->toIso8601String(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * A signed "no", so the site stops even though it cannot be faked.
     *
     * @return array{payload: string, signature: string}
     */
    public function revoked(string $product, string $code, string $domain, string $message): array
    {
        return $this->sign([
            'product' => $product,
            'code' => $code,
            'domain' => $domain,
            'status' => 'revoked',
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{payload: string, signature: string}
     */
    private function sign(array $data): array
    {
        $key = base64_decode((string) config('licensing.server.signing_key'), true);

        if (! $key) {
            throw new RuntimeException('LICENSE_SIGNING_KEY is not set on the license server.');
        }

        return Certificate::sign($data, $key);
    }
}
