<?php

namespace App\LicenseServer\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:keygen {--server= : Address of your license server, e.g. https://lesen.example.com} {--force : Replace an existing key (every sold copy stops working)}')]
#[Description('Create the key pair that signs licenses, and point the app at your license server')]
class KeygenCommand extends Command
{
    public function handle(): int
    {
        $config = config_path('licensing.php');
        $contents = (string) file_get_contents($config);
        $hasKey = trim((string) config('licensing.public_key')) !== '';

        if ($hasKey && ! $this->option('force')) {
            $this->components->error('A public key is already set in config/licensing.php.');
            $this->line('  Replacing it makes every copy already sold stop working. Use --force only if you really mean that.');

            return self::FAILURE;
        }

        $server = rtrim((string) ($this->option('server') ?: $this->ask('Address of your license server (e.g. https://lesen.example.com)')), '/');

        if (! filter_var($server, FILTER_VALIDATE_URL) || ! str_starts_with($server, 'https://')) {
            $this->components->error('Give the full https:// address of the license server.');

            return self::FAILURE;
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        $contents = preg_replace("/'public_key' => '[^']*',/", "'public_key' => ".var_export(trim($public), true).',', $contents, 1);
        $contents = preg_replace("/'server_url' => env\\('LICENSE_SERVER_URL', '[^']*'\\),/", "'server_url' => env('LICENSE_SERVER_URL', ".var_export($server, true).'),', (string) $contents, 1);
        file_put_contents($config, $contents);

        $this->components->info('config/licensing.php now has your public key and license server address. Commit it.');
        $this->newLine();
        $this->line('  On the <options=bold>license server</> only, add these two lines to its .env:');
        $this->newLine();
        $this->line('  LICENSE_SERVER_ENABLED=true');
        $this->line('  LICENSE_SIGNING_KEY='.base64_encode($private));
        $this->newLine();
        $this->components->warn('Keep a safe copy of LICENSE_SIGNING_KEY (a password manager). If it is lost, no new site can be activated and existing ones stop within 30 days. Never commit it.');

        return self::SUCCESS;
    }
}
