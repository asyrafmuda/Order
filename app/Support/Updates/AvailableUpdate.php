<?php

namespace App\Support\Updates;

/**
 * A release the license server offers this site, as last checked.
 */
final readonly class AvailableUpdate
{
    /**
     * @param  array{payload: string, signature: string}  $manifest
     */
    public function __construct(
        public string $version,
        public ?string $notes,
        public int $size,
        public ?string $publishedAt,
        public string $downloadUrl,
        public array $manifest,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['version'], $data['download_url'], $data['manifest']['payload'], $data['manifest']['signature'])) {
            return null;
        }

        return new self(
            (string) $data['version'],
            isset($data['notes']) ? (string) $data['notes'] : null,
            (int) ($data['size'] ?? 0),
            isset($data['published_at']) ? (string) $data['published_at'] : null,
            (string) $data['download_url'],
            ['payload' => (string) $data['manifest']['payload'], 'signature' => (string) $data['manifest']['signature']],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'notes' => $this->notes,
            'size' => $this->size,
            'published_at' => $this->publishedAt,
            'download_url' => $this->downloadUrl,
            'manifest' => $this->manifest,
        ];
    }
}
