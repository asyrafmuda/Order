<?php

namespace App\Support\Licensing;

use Carbon\CarbonImmutable;

/**
 * What the license server says about one purchase code on one domain,
 * signed with the seller's private key. Nothing in it can be changed
 * without the signature failing.
 *
 * Payload: product, code, domain, customer, status (active|revoked),
 * message, issued_at, checked_at.
 */
final readonly class Certificate
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public string $payload,
        public string $signature,
        public array $data,
    ) {}

    /**
     * Read a certificate as the server sent it. Returns null unless the
     * signature matches the public key.
     */
    public static function verify(string $payload, string $signature, string $publicKey): ?self
    {
        $key = @openssl_pkey_get_public($publicKey);
        $raw = self::decode($signature);

        if ($key === false || $raw === null || openssl_verify($payload, $raw, $key, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $data = json_decode((string) self::decode($payload), true);

        return is_array($data) ? new self($payload, $signature, $data) : null;
    }

    /**
     * Sign a payload with a private key (PEM). Used by the license server,
     * and by tests standing in for it.
     *
     * @param  array<string, mixed>  $data
     * @return array{payload: string, signature: string}
     */
    public static function sign(array $data, string $privateKey): array
    {
        $payload = self::encode((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('The license signing key could not be used.');
        }

        return ['payload' => $payload, 'signature' => self::encode($signature)];
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function domain(): string
    {
        return (string) $this->get('domain');
    }

    public function isRevoked(): bool
    {
        return $this->get('status') !== 'active';
    }

    public function checkedAt(): ?CarbonImmutable
    {
        $at = $this->get('checked_at');

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    /**
     * @return array{payload: string, signature: string}
     */
    public function toArray(): array
    {
        return ['payload' => $this->payload, 'signature' => $this->signature];
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
