<?php

namespace App\Support\Installer;

use RuntimeException;

/**
 * Reads and writes the .env file.
 *
 * A release ZIP never contains a .env, so uploading an update cannot overwrite
 * a site's settings. On the first web request the file is created from
 * .env.example with a fresh application key; the installer fills in the rest.
 */
final class EnvironmentFile
{
    public function __construct(private readonly string $path) {}

    /**
     * Called before the framework boots, for web requests only: make sure a
     * .env with an application key exists, so cookies and the installer work.
     * Runs without any of Laravel, so it uses plain PHP.
     */
    public static function prepare(string $basePath, ?bool $web = null): void
    {
        if (! ($web ?? (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg'))) {
            return;
        }

        $path = $basePath.'/.env';
        $example = $basePath.'/.env.example';

        if (! is_file($path)) {
            if (! is_file($example) || ! is_writable($basePath)) {
                return;
            }

            $file = new self($path);
            $file->write((string) file_get_contents($example));
            $file->set([
                'APP_KEY' => self::newKey(),
                // Safe defaults until the installer has run.
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
                // Fixed, so renaming the shop (APP_NAME) never signs everyone out.
                'SESSION_COOKIE' => 'order_session',
            ]);

            return;
        }

        // A .env copied by hand from .env.example has no key yet.
        $file = new self($path);

        if ($file->get('APP_KEY') === '' && is_writable($path)) {
            $file->set(['APP_KEY' => self::newKey()]);
        }
    }

    public static function newKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        foreach (preg_split('/\R/', (string) file_get_contents($this->path)) as $line) {
            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=(.*)$/', $line, $match)) {
                return self::unquote(trim($match[1]));
            }
        }

        return null;
    }

    /**
     * Set the given keys, keeping every other line (and comment) as it is.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->exists() ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Invalid environment key [{$key}].");
            }

            $line = $key.'='.self::quote($value);
            $pattern = '/^\s*'.preg_quote($key, '/').'\s*=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents, "\r\n")."\n".$line."\n";
        }

        $this->write($contents);
    }

    public function write(string $contents): void
    {
        $directory = dirname($this->path);
        $temporary = @tempnam($directory, '.env-');

        // Write next to the file and move it into place, so a failed write can
        // never leave a half-written .env behind.
        if ($temporary === false || @file_put_contents($temporary, $contents) === false || ! @rename($temporary, $this->path)) {
            if ($temporary !== false) {
                @unlink($temporary);
            }

            if (@file_put_contents($this->path, $contents) === false) {
                throw new RuntimeException("Could not write {$this->path}. Make sure the app folder is writable.");
            }
        }

        @chmod($this->path, 0640);
    }

    private static function quote(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=,-]+$/', $value)) {
            return $value;
        }

        // Inside double quotes .env reads \" and \\ as escapes and expands
        // ${VAR}, so those characters are escaped.
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return str_replace(['\\"', '\\$', '\\\\'], ['"', '$', '\\'], substr($value, 1, -1));
        }

        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return preg_replace('/\s+#.*$/', '', $value) ?? $value;
    }
}
