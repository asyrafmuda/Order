<?php

namespace App\Support\Installer;

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether the app has been installed, and which version its database is at.
 *
 * The state lives in a small lock file (config installer.lock) rather than
 * the database, so it can be read before a database is configured and costs
 * nothing per request.
 */
class Installation
{
    /** @var array{version?: string, installed_at?: string, updated_at?: string, via?: string}|null|false */
    private array|false|null $lock = false;

    private ?bool $installed = null;

    /**
     * The version of the code on disk, from the VERSION file. Read directly
     * (not through config) so a stale config cache cannot hide an update.
     */
    public function version(): string
    {
        $file = (string) config('installer.version_file');

        return is_file($file) ? trim((string) file_get_contents($file)) ?: '0.0.0' : '0.0.0';
    }

    public function isInstalled(): bool
    {
        return $this->installed ??= $this->lock() !== null || $this->adoptExisting();
    }

    /**
     * The version the database was last brought up to.
     */
    public function installedVersion(): ?string
    {
        return $this->lock()['version'] ?? null;
    }

    public function installedAt(): ?CarbonImmutable
    {
        $at = $this->lock()['installed_at'] ?? null;

        return $at ? CarbonImmutable::parse($at) : null;
    }

    /**
     * Whether newer files were uploaded whose database changes have not run
     * yet. A new version without database changes is simply recorded.
     */
    public function needsUpdate(): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        $installed = $this->installedVersion() ?? '0.0.0';

        if (version_compare($installed, $this->version(), '>=')) {
            return false;
        }

        if ($this->pendingMigrations() === []) {
            $this->markInstalled();

            return false;
        }

        return true;
    }

    /**
     * Names of migrations on disk that have not run yet.
     *
     * @return array<int, string>
     */
    public function pendingMigrations(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return array_keys($migrator->getMigrationFiles(database_path('migrations')));
        }

        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran));
    }

    /**
     * Record that the database is at the current version, or at $version.
     */
    public function markInstalled(string $via = 'installer', ?string $version = null): void
    {
        $now = now()->toIso8601String();
        $lock = $this->lock() ?? [];

        $this->writeLock([
            'version' => $version ?? $this->version(),
            'installed_at' => $lock['installed_at'] ?? $now,
            'updated_at' => $now,
            'via' => $lock['via'] ?? $via,
        ]);
    }

    /**
     * After `php artisan migrate` (or the update page), the database matches
     * the code again.
     */
    public function syncAfterMigrations(): void
    {
        if ($this->lock() !== null && $this->pendingMigrations() === []) {
            $this->markInstalled();
        }
    }

    /**
     * Forget what was read, e.g. after the installer wrote the lock file.
     */
    public function refresh(): void
    {
        $this->lock = false;
        $this->installed = null;
    }

    /**
     * @return array{version?: string, installed_at?: string, updated_at?: string, via?: string}|null
     */
    private function lock(): ?array
    {
        if ($this->lock !== false) {
            return $this->lock;
        }

        $path = (string) config('installer.lock');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return $this->lock = is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function writeLock(array $data): void
    {
        $path = (string) config('installer.lock');

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);

        $this->lock = $data;
        $this->installed = true;
    }

    /**
     * A site set up before the installer existed (or from the command line)
     * has a working database with this app's tables. Record it as installed
     * instead of sending the owner to the installer. Pending migrations leave
     * it at version 0.0.0, so the update page runs them.
     */
    private function adoptExisting(): bool
    {
        if (! config('installer.adopt_existing')) {
            return false;
        }

        try {
            if (! DB::connection()->getSchemaBuilder()->hasTable('migrations') || ! DB::table('migrations')->exists()) {
                return false;
            }

            $pending = $this->pendingMigrations() !== [];
        } catch (Throwable) {
            // No database configured yet: this is a fresh upload.
            return false;
        }

        $this->markInstalled('existing', $pending ? '0.0.0' : null);

        return true;
    }
}
