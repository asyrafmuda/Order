<?php

namespace App\Support\Updates;

use App\Support\Installer\Installation;
use App\Support\Licensing\Certificate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * One-click updates: downloads a release from the license server, checks
 * it against the seller's signature, works out what changes, keeps a
 * backup of every file it replaces, puts the new files in place, and then
 * (in a fresh request, running the new code) runs the database changes.
 *
 * If anything fails while files are being replaced, the backup is put back
 * and the site is left as it was.
 */
class Updater
{
    /** The file list every release carries, relative to the app folder. */
    public const MANIFEST = 'bootstrap/release-manifest.json';

    /** Customers see the "being updated" page for at most this long. */
    private const APPLYING_MINUTES = 15;

    /** Written last, so the site only sees the new version once its files are in place. */
    private const LAST = [self::MANIFEST, 'VERSION'];

    public function __construct(private readonly Installation $installation) {}

    public static function workDir(): string
    {
        return rtrim((string) config('installer.updates.work_dir'), '/');
    }

    /**
     * Whether files are being replaced right now (customers are held back).
     */
    public static function applying(): bool
    {
        $flag = self::workDir().'/applying';

        return is_file($flag) && filemtime($flag) > time() - self::APPLYING_MINUTES * 60;
    }

    /**
     * Reasons this copy cannot update itself; empty when it can.
     *
     * @return list<string>
     */
    public function blockers(): array
    {
        $blockers = [];

        if (! class_exists(ZipArchive::class)) {
            $blockers[] = 'The PHP zip extension is switched off on this server. Switch it on (cPanel → Select PHP Version → Extensions → zip), or update by uploading the ZIP yourself.';
        }

        if (is_dir($this->root().'/.git')) {
            $blockers[] = 'This copy is a git checkout. Update it with git pull, then php artisan migrate.';
        }

        if (! is_writable($this->root())) {
            $blockers[] = 'PHP may not write to the app folder, so it cannot replace files. Ask your host to give the PHP user write access, or update by uploading the ZIP yourself.';
        }

        return $blockers;
    }

    /**
     * Download and check the release, and work out what installing it does.
     *
     * @throws RuntimeException with a message for the site owner
     */
    public function prepare(AvailableUpdate $update): UpdatePlan
    {
        if ($blockers = $this->blockers()) {
            throw new RuntimeException($blockers[0]);
        }

        $signed = $this->verifyManifest($update);
        $from = $this->installation->version();

        if (version_compare($signed['version'], $from, '<=')) {
            throw new RuntimeException("Version {$signed['version']} is not newer than this site ({$from}).");
        }

        @set_time_limit(0);
        $this->ensureWorkDir();
        $free = @disk_free_space($this->root());

        if (is_float($free) && $free < $signed['size'] * 4) {
            throw new RuntimeException('There is not enough free disk space for the update and its backup (about '.self::megabytes($signed['size'] * 4).' needed).');
        }

        $zipPath = self::workDir().'/release-'.$signed['version'].'.zip';

        if (! is_file($zipPath) || hash_file('sha256', $zipPath) !== $signed['sha256']) {
            $this->download($update->downloadUrl, $zipPath);
        }

        if (filesize($zipPath) !== $signed['size'] || hash_file('sha256', $zipPath) !== $signed['sha256']) {
            @unlink($zipPath);

            throw new RuntimeException('The downloaded update does not match the one the seller signed, so it was thrown away. Try again; if it keeps happening, contact the seller.');
        }

        $plan = $this->plan($zipPath, $signed, $from);
        file_put_contents($this->planFile(), json_encode($plan->toArray(), JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $plan;
    }

    /**
     * The plan prepare() saved, while it is still current.
     */
    public function savedPlan(): ?UpdatePlan
    {
        $data = is_file($this->planFile()) ? json_decode((string) file_get_contents($this->planFile()), true) : null;

        if (! is_array($data)) {
            return null;
        }

        $plan = UpdatePlan::fromArray($data);

        return $plan->from === $this->installation->version() && is_file($plan->zip) ? $plan : null;
    }

    /**
     * Put the new files in place. Returns the address that finishes the
     * update (database changes) in a fresh request.
     *
     * @throws RuntimeException
     * @throws ChangedFilesException when the owner edited files and has not agreed to replace them
     */
    public function apply(UpdatePlan $plan, bool $replaceChanged = false): string
    {
        @set_time_limit(0);
        $lock = $this->lock();

        try {
            if ($plan->from !== $this->installation->version()) {
                throw new RuntimeException('This site changed since the update was checked. Check for updates again.');
            }

            if (! is_file($plan->zip) || hash_file('sha256', $plan->zip) !== $plan->sha256) {
                throw new RuntimeException('The downloaded update is gone or changed. Check for updates again.');
            }

            if ($plan->changed !== [] && ! $replaceChanged) {
                throw new ChangedFilesException($plan->changed);
            }

            if ($unwritable = $this->unwritable([...$plan->writes, ...$plan->deletes])) {
                throw new RuntimeException('PHP may not change these files or folders: '.implode(', ', array_slice($unwritable, 0, 5)).(count($unwritable) > 5 ? '…' : '').'. Ask your host to fix the permissions, then try again.');
            }

            touch(self::workDir().'/applying');
            $backup = $this->backup($plan);

            try {
                $this->install($plan);
            } catch (Throwable $exception) {
                $this->restore($backup);
                @unlink(self::workDir().'/applying');
                Log::error('Update failed and was rolled back', ['exception' => $exception]);

                throw new RuntimeException('The update could not be installed and the site was put back as it was: '.$exception->getMessage());
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            clearstatcache(true);
            $token = bin2hex(random_bytes(20));
            file_put_contents($this->finishFile(), json_encode([
                'token' => hash('sha256', $token),
                'from' => $plan->from,
                'to' => $plan->to,
                'backup' => $backup,
                'at' => time(),
            ]), LOCK_EX);

            return route('install.update.finish', ['token' => $token]);
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Runs in a fresh request after apply(), on the new code: database
     * changes, caches, and clean-up. Returns the new version.
     *
     * @throws RuntimeException
     */
    public function finish(string $token): string
    {
        $data = is_file($this->finishFile()) ? json_decode((string) file_get_contents($this->finishFile()), true) : null;

        if (! is_array($data) || ! hash_equals((string) ($data['token'] ?? ''), hash('sha256', $token)) || (int) ($data['at'] ?? 0) < time() - 3600) {
            throw new RuntimeException('This update link is not valid (any more).');
        }

        @set_time_limit(0);
        @unlink($this->finishFile());

        try {
            if ($this->installation->pendingMigrations() !== [] && Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException(trim(Artisan::output()));
            }

            Artisan::call('optimize:clear');
            $this->installation->markInstalled('update');
        } finally {
            @unlink(self::workDir().'/applying');
        }

        $this->discard();
        app(UpdateServer::class)->forget();
        $this->pruneBackups();

        return $this->installation->version();
    }

    /**
     * Throw away a downloaded release and its plan.
     */
    public function discard(): void
    {
        foreach (glob(self::workDir().'/release-*.zip') ?: [] as $zip) {
            @unlink($zip);
        }

        @unlink($this->planFile());
    }

    /**
     * Backups of replaced files, newest first.
     *
     * @return list<array{name: string, size: int, at: int}>
     */
    public function backups(): array
    {
        $files = glob(self::workDir().'/backups/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn ($file) => ['name' => basename($file), 'size' => (int) filesize($file), 'at' => (int) filemtime($file)], $files);
    }

    /**
     * @return array{version: string, sha256: string, size: int}
     */
    private function verifyManifest(AvailableUpdate $update): array
    {
        $certificate = Certificate::verify($update->manifest['payload'], $update->manifest['signature'], (string) config('licensing.public_key'));

        if ($certificate === null || $certificate->get('type') !== 'release' || $certificate->get('product') !== config('licensing.product')
            || $certificate->get('version') !== $update->version || ! preg_match('/^[0-9a-f]{64}$/', (string) $certificate->get('sha256'))) {
            throw new RuntimeException('The update is not signed by the seller, so it was not installed.');
        }

        return ['version' => (string) $certificate->get('version'), 'sha256' => (string) $certificate->get('sha256'), 'size' => (int) $certificate->get('size')];
    }

    private function download(string $url, string $target): void
    {
        $partial = $target.'.part';
        @unlink($partial);

        try {
            $response = Http::withOptions(['sink' => $partial])->timeout(900)->connectTimeout(10)->get($url);
        } catch (Throwable) {
            @unlink($partial);

            throw new RuntimeException('The update could not be downloaded. Check that this server can make outgoing HTTPS connections, then try again.');
        }

        if ($response->failed()) {
            $message = json_decode((string) @file_get_contents($partial), true)['message'] ?? null;
            @unlink($partial);

            throw new RuntimeException($message ?: 'The update server refused the download (HTTP '.$response->status().'). Check for updates again.');
        }

        // Some HTTP stacks hand the body back instead of writing it to the file.
        if (! is_file($partial) || filesize($partial) === 0) {
            file_put_contents($partial, $response->body());
        }

        rename($partial, $target);
    }

    /**
     * @param  array{version: string, sha256: string, size: int}  $signed
     */
    private function plan(string $zipPath, array $signed, string $from): UpdatePlan
    {
        $zip = $this->openZip($zipPath);

        try {
            $new = json_decode((string) $zip->getFromName(self::MANIFEST), true);

            if (! is_array($new) || ($new['version'] ?? null) !== $signed['version'] || ! is_array($new['files'] ?? null)
                || trim((string) $zip->getFromName('VERSION')) !== $signed['version']) {
                throw new RuntimeException('The update package is incomplete (no file list). Contact the seller.');
            }

            $old = $this->currentManifest();
            $newFiles = $this->safePaths($new['files']);
            $oldFiles = $old ? $this->safePaths($old['files']) : [];
            $writes = [];
            $changed = [];

            foreach ($newFiles as $path => $hash) {
                if ($zip->locateName($path) === false) {
                    throw new RuntimeException("The update package is missing {$path}. Contact the seller.");
                }

                $current = $this->root().'/'.$path;
                $exists = is_file($current);

                // The release did not change this file: leave it as it is, edited or not.
                if ($exists && ($oldFiles[$path] ?? null) === $hash) {
                    continue;
                }

                $currentHash = $exists ? hash_file('sha256', $current) : null;

                if ($currentHash === $hash) {
                    continue;
                }

                $writes[] = $path;

                if ($old && $exists && ! self::generated($path) && $currentHash !== ($oldFiles[$path] ?? null)) {
                    $changed[] = $path;
                }
            }

            $deletes = [];

            foreach ($oldFiles as $path => $hash) {
                if (isset($newFiles[$path]) || ! is_file($this->root().'/'.$path)) {
                    continue;
                }

                $deletes[] = $path;

                if (! self::generated($path) && hash_file('sha256', $this->root().'/'.$path) !== $hash) {
                    $changed[] = $path;
                }
            }

            // The file list is always rewritten, even when nothing else changed.
            $writes[] = self::MANIFEST;
            $writes = $this->ordered(array_values(array_unique($writes)));
            sort($changed);

            return new UpdatePlan(
                from: $from,
                to: $signed['version'],
                zip: $zipPath,
                sha256: $signed['sha256'],
                writes: $writes,
                deletes: $deletes,
                changed: $changed,
                unwritable: $this->unwritable([...$writes, ...$deletes]),
                baseline: $old !== null,
                createdAt: now()->toIso8601String(),
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * Copies of every file the update replaces or removes, plus the list of
     * files it adds, so a failed update can be undone.
     */
    private function backup(UpdatePlan $plan): string
    {
        $dir = self::workDir().'/backups';

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/backup-'.$plan->from.'-before-'.$plan->to.'-'.date('Ymd-His').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup in '.$dir.'. Nothing was changed.');
        }

        $added = [];

        foreach ([...$plan->writes, ...$plan->deletes] as $file) {
            $absolute = $this->root().'/'.$file;

            if (is_file($absolute)) {
                $zip->addFile($absolute, $file);
            } else {
                $added[] = $file;
            }
        }

        $zip->addFromString('.update-added.json', (string) json_encode($added));

        // Files are read when the archive is closed: it must be complete before anything changes.
        if (! $zip->close()) {
            @unlink($path);

            throw new RuntimeException('The backup could not be written (is the disk full?). Nothing was changed.');
        }

        return $path;
    }

    private function install(UpdatePlan $plan): void
    {
        $zip = $this->openZip($plan->zip);

        try {
            foreach ($plan->deletes as $file) {
                $absolute = $this->root().'/'.$file;

                if (is_file($absolute) && ! @unlink($absolute)) {
                    throw new RuntimeException("Could not remove {$file}.");
                }
            }

            foreach ($plan->writes as $file) {
                $this->extract($zip, $file);
            }
        } finally {
            $zip->close();
        }
    }

    private function extract(ZipArchive $zip, string $file): void
    {
        $target = $this->root().'/'.$file;
        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create the folder for {$file}.");
        }

        $source = $zip->getStream($file);

        if ($source === false) {
            throw new RuntimeException("Could not read {$file} from the update.");
        }

        $temporary = $target.'.update-tmp';
        $out = @fopen($temporary, 'wb');

        if ($out === false) {
            fclose($source);

            throw new RuntimeException("Could not write {$file}.");
        }

        $copied = stream_copy_to_stream($source, $out);
        fclose($source);
        fclose($out);

        if ($copied === false || ! @rename($temporary, $target)) {
            @unlink($temporary);

            throw new RuntimeException("Could not write {$file}.");
        }

        @chmod($target, 0644);
    }

    /**
     * Put every backed-up file back and remove the ones the update added.
     */
    private function restore(string $backup): void
    {
        $zip = $this->openZip($backup);

        try {
            $added = json_decode((string) $zip->getFromName('.update-added.json'), true) ?: [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);

                if ($name !== '.update-added.json') {
                    $this->extract($zip, $name);
                }
            }

            foreach ($added as $file) {
                @unlink($this->root().'/'.$file);
                @unlink($this->root().'/'.$file.'.update-tmp');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{version: string, files: array<string, string>}|null
     */
    private function currentManifest(): ?array
    {
        $file = $this->root().'/'.self::MANIFEST;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_array($data['files'] ?? null) ? $data : null;
    }

    /**
     * Only plain relative paths inside the app folder, never a site's own data.
     *
     * @param  array<mixed, mixed>  $files
     * @return array<string, string>
     */
    private function safePaths(array $files): array
    {
        $safe = [];

        foreach ($files as $path => $hash) {
            $path = (string) $path;

            if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")
                || in_array('..', explode('/', $path), true) || ! is_string($hash) || self::protected($path)) {
                continue;
            }

            $safe[$path] = $hash;
        }

        return $safe;
    }

    private static function protected(string $path): bool
    {
        return $path === '.env' || $path === self::MANIFEST
            || str_starts_with($path, 'storage/') || $path === 'public/storage' || str_starts_with($path, 'public/storage/')
            || preg_match('#^bootstrap/cache/(config|routes-v7|events)\.php$#', $path) === 1;
    }

    private static function generated(string $path): bool
    {
        return str_starts_with($path, 'vendor/') || str_starts_with($path, 'public/build/') || str_starts_with($path, 'bootstrap/cache/');
    }

    /**
     * @param  list<string>  $writes
     * @return list<string>
     */
    private function ordered(array $writes): array
    {
        $last = array_values(array_intersect(self::LAST, $writes));

        return [...array_values(array_diff($writes, self::LAST)), ...$last];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function unwritable(array $paths): array
    {
        $unwritable = [];
        $checked = [];

        foreach ($paths as $path) {
            $absolute = $this->root().'/'.$path;

            if (file_exists($absolute)) {
                if (! is_writable($absolute) || ! is_writable(dirname($absolute))) {
                    $unwritable[] = $path;
                }

                continue;
            }

            // A new file: the nearest folder that exists must take it.
            $directory = dirname($absolute);

            while (! is_dir($directory) && $directory !== dirname($directory)) {
                $directory = dirname($directory);
            }

            if (! isset($checked[$directory])) {
                $checked[$directory] = is_writable($directory);
            }

            if (! $checked[$directory]) {
                $unwritable[] = $path;
            }
        }

        return $unwritable;
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The update package could not be opened.');
        }

        return $zip;
    }

    /**
     * @return resource
     */
    private function lock()
    {
        $this->ensureWorkDir();
        $handle = fopen(self::workDir().'/update.lock', 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another update is running. Wait a minute and refresh.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function pruneBackups(): void
    {
        foreach (array_slice($this->backups(), (int) config('installer.updates.keep_backups', 3)) as $old) {
            @unlink(self::workDir().'/backups/'.$old['name']);
        }
    }

    private function ensureWorkDir(): void
    {
        if (! is_dir(self::workDir())) {
            mkdir(self::workDir(), 0775, true);
        }
    }

    private function root(): string
    {
        return rtrim((string) config('installer.updates.root'), '/');
    }

    private function planFile(): string
    {
        return self::workDir().'/plan.json';
    }

    private function finishFile(): string
    {
        return self::workDir().'/finish.json';
    }

    private static function megabytes(int $bytes): string
    {
        return round($bytes / 1048576).' MB';
    }
}
