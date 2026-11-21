<?php

namespace App\Support\Updates;

use App\Support\Installer\Installation;
use App\Support\Licensing\License;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Asks the license server whether a newer release is out for this site,
 * and remembers the answer so the back office can show it without asking
 * again on every page.
 */
class UpdateServer
{
    public function __construct(
        private readonly License $license,
        private readonly Installation $installation,
    ) {}

    /**
     * Whether this copy can update itself at all.
     */
    public function supported(): bool
    {
        return $this->license->configured() && $this->license->certificate() !== null;
    }

    /**
     * Ask the license server now.
     *
     * @return array{update: ?AvailableUpdate, locked: ?array{version: string, updates_until: ?string}, checked_at: string}
     *
     * @throws RuntimeException with a message for the site owner
     */
    public function check(): array
    {
        $certificate = $this->license->certificate();
        $server = rtrim((string) config('licensing.server_url'), '/');

        if (! $this->license->configured() || $certificate === null || $server === '') {
            throw new RuntimeException('Updates come with an activated license. Activate it in Admin → License first.');
        }

        try {
            $response = Http::acceptJson()->timeout(20)->connectTimeout(8)->post("{$server}/api/license/updates", [
                'code' => $certificate->get('code'),
                'domain' => $certificate->domain(),
                'product' => config('licensing.product'),
                'version' => $this->installation->version(),
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach the update server. Check that this server can make outgoing HTTPS connections, then try again.');
        }

        if ($response->failed()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'The update server could not answer (HTTP '.$response->status().'). Try again later.'));
        }

        $result = [
            'update' => is_array($response->json('update')) ? AvailableUpdate::fromArray($response->json('update')) : null,
            'locked' => is_array($response->json('locked')) ? [
                'version' => (string) $response->json('locked.version'),
                'updates_until' => $response->json('locked.updates_until'),
            ] : null,
            'checked_at' => now()->toIso8601String(),
        ];

        // Never offer what is not newer than what runs here.
        if ($result['update'] && version_compare($result['update']->version, $this->installation->version(), '<=')) {
            $result['update'] = null;
        }

        $this->remember($result);

        return $result;
    }

    /**
     * Check from the scheduler; a failure is only logged.
     */
    public function checkQuietly(): void
    {
        if (! $this->supported()) {
            return;
        }

        try {
            $this->check();
        } catch (Throwable $exception) {
            Log::warning('Update check failed: '.$exception->getMessage());
        }
    }

    /**
     * The answer of the last check, if it still applies to the version on disk.
     *
     * @return array{update: ?AvailableUpdate, locked: ?array{version: string, updates_until: ?string}, checked_at: ?string}
     */
    public function last(): array
    {
        $file = $this->file();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $data = is_array($data) ? $data : [];
        $update = is_array($data['update'] ?? null) ? AvailableUpdate::fromArray($data['update']) : null;
        $locked = is_array($data['locked'] ?? null) ? $data['locked'] : null;
        $current = $this->installation->version();

        return [
            'update' => $update && version_compare($update->version, $current, '>') ? $update : null,
            'locked' => $locked && version_compare((string) ($locked['version'] ?? '0'), $current, '>') ? $locked : null,
            'checked_at' => $data['checked_at'] ?? null,
        ];
    }

    /**
     * Forget the last answer (after updating).
     */
    public function forget(): void
    {
        @unlink($this->file());
    }

    /**
     * @param  array{update: ?AvailableUpdate, locked: mixed, checked_at: string}  $result
     */
    private function remember(array $result): void
    {
        $file = $this->file();

        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }

        file_put_contents($file, json_encode([...$result, 'update' => $result['update']?->toArray()], JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function file(): string
    {
        return Updater::workDir().'/latest.json';
    }
}
