<?php

namespace App\Support\Installer;

/**
 * What the server must offer. Shown on the first installer screen, so a host
 * that cannot run the app says so before anything is written.
 */
class Requirements
{
    /**
     * @return array<int, array{group: string, label: string, ok: bool, required: bool, detail: string}>
     */
    public function checks(): array
    {
        $checks = [];
        $minimum = (string) config('installer.php');

        $checks[] = [
            'group' => 'PHP',
            'label' => "PHP {$minimum} or newer",
            'ok' => version_compare(PHP_VERSION, $minimum, '>='),
            'required' => true,
            'detail' => 'This server runs PHP '.PHP_VERSION.'. In cPanel: Software → MultiPHP Manager (or Select PHP Version).',
        ];

        foreach ((array) config('installer.extensions') as $extension) {
            $checks[] = [
                'group' => 'PHP extensions',
                'label' => $extension,
                'ok' => extension_loaded($extension),
                'required' => true,
                'detail' => 'Turn it on under Select PHP Version → Extensions, or ask your host.',
            ];
        }

        foreach ((array) config('installer.recommended_extensions') as $extension => $why) {
            $checks[] = [
                'group' => 'PHP extensions',
                'label' => "{$extension} (recommended)",
                'ok' => extension_loaded($extension),
                'required' => false,
                'detail' => $why,
            ];
        }

        foreach ((array) config('installer.writable') as $folder) {
            $checks[] = [
                'group' => 'Folders',
                'label' => "{$folder} is writable",
                'ok' => is_dir(base_path($folder)) && is_writable(base_path($folder)),
                'required' => true,
                'detail' => 'Set the folder permission to 755 (or 775) in the File Manager.',
            ];
        }

        $env = base_path('.env');
        $checks[] = [
            'group' => 'Folders',
            'label' => '.env settings file can be saved',
            'ok' => is_file($env) ? is_writable($env) : is_writable(base_path()),
            'required' => true,
            'detail' => 'The installer saves the database details in the .env file in the app folder. Make the app folder (or .env) writable.',
        ];

        $memory = $this->bytes((string) ini_get('memory_limit'));
        $checks[] = [
            'group' => 'PHP',
            'label' => 'Memory limit of 128 MB or more',
            'ok' => $memory < 0 || $memory >= 128 * 1024 * 1024,
            'required' => false,
            'detail' => 'Currently '.ini_get('memory_limit').'. Raise memory_limit in Select PHP Version → Options.',
        ];

        return $checks;
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
