<?php

namespace App\LicenseServer;

use App\LicenseServer\Models\Release;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Checks an update ZIP before customers can install it, and files it away.
 * Refuses anything that would hurt every site that installs it: a ZIP that
 * is not a release build, one built without the license check or with
 * another key, or one that still holds the license server.
 */
class ReleasePackage
{
    /**
     * @return array{version: string, size: int, sha256: string}
     *
     * @throws RuntimeException with a message for the seller
     */
    public function inspect(string $zipPath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed on the license server to check update packages.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('This is not a ZIP file, or it is damaged.');
        }

        try {
            $version = trim((string) $zip->getFromName('VERSION'));
            $manifest = json_decode((string) $zip->getFromName('bootstrap/release-manifest.json'), true);

            if ($version === '' || ! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new RuntimeException('The ZIP has no VERSION file with a version like 1.2.0. Upload the ZIP made by scripts/package.sh, not a folder you zipped yourself.');
            }

            if (! is_array($manifest) || ($manifest['version'] ?? null) !== $version || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('The ZIP has no file list (bootstrap/release-manifest.json). Build it again with the current scripts/package.sh.');
            }

            if ($zip->locateName('vendor/autoload.php') === false || $zip->locateName('public/build/manifest.json') === false) {
                throw new RuntimeException('The ZIP is missing the PHP packages or the built JavaScript. Build it with scripts/package.sh.');
            }

            if ($zip->locateName('app/LicenseServer/Signer.php') !== false) {
                throw new RuntimeException('The ZIP contains the license server. Customers must never get it: build the ZIP with scripts/package.sh.');
            }

            $this->checkPublicKey((string) $zip->getFromName('config/licensing.php'));
        } finally {
            $zip->close();
        }

        return ['version' => $version, 'size' => (int) filesize($zipPath), 'sha256' => (string) hash_file('sha256', $zipPath)];
    }

    /**
     * Check and store an uploaded ZIP as a new release.
     *
     * @throws RuntimeException
     */
    public function store(string $zipPath, ?string $notes, bool $publish, string $audience = Release::AUDIENCE_ALL): Release
    {
        $info = $this->inspect($zipPath);
        $product = (string) config('licensing.product');

        if (Release::query()->where('product', $product)->where('version', $info['version'])->exists()) {
            throw new RuntimeException("Version {$info['version']} was uploaded already. Bump the VERSION file, build again and upload the new ZIP.");
        }

        $path = "releases/{$product}-{$info['version']}-".bin2hex(random_bytes(4)).'.zip';
        $stream = fopen($zipPath, 'rb');

        try {
            Storage::disk('local')->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return Release::query()->create([
            'product' => $product,
            'version' => $info['version'],
            'notes' => $notes,
            'path' => $path,
            'size' => $info['size'],
            'sha256' => $info['sha256'],
            'audience' => $audience,
            'published_at' => $publish ? now() : null,
        ]);
    }

    private function checkPublicKey(string $config): void
    {
        $ours = self::normalizeKey((string) config('licensing.public_key'));

        preg_match("/'public_key'\s*=>\s*'([^']*)'/", $config, $match);
        $theirs = self::normalizeKey($match[1] ?? '');

        if ($theirs === '') {
            throw new RuntimeException('This ZIP was built without the license check (ALLOW_UNLICENSED). Sites that install it would stop checking their license. Build it again normally.');
        }

        if ($ours !== '' && $theirs !== $ours) {
            throw new RuntimeException('This ZIP carries a different license key from this server. Sites that install it could no longer be licensed. Build it from the same project as this server.');
        }
    }

    private static function normalizeKey(string $key): string
    {
        return preg_replace('/\s+/', '', str_replace(['\n', '\\n'], '', $key)) ?? '';
    }
}
