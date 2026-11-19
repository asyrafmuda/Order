<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('shop:enable-realtime {--force : Replace existing Reverb credentials}')]
#[Description('Generate Reverb credentials in .env and switch broadcasting to Reverb')]
class EnableRealtimeCommand extends Command
{
    public function handle(): int
    {
        $path = $this->laravel->environmentFilePath();

        if (! File::exists($path)) {
            $this->components->error('No .env file found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $env = File::get($path);

        if (filled(config('reverb.apps.apps.0.key')) && ! $this->option('force')) {
            $this->components->info('Reverb credentials already exist. Use --force to replace them.');
        } else {
            $env = $this->set($env, 'REVERB_APP_ID', (string) random_int(100_000, 999_999));
            $env = $this->set($env, 'REVERB_APP_KEY', Str::lower(Str::random(20)));
            $env = $this->set($env, 'REVERB_APP_SECRET', Str::lower(Str::random(20)));
        }

        File::put($path, $this->set($env, 'BROADCAST_CONNECTION', 'reverb'));

        $this->components->info('Real-time updates enabled. Start the websocket server with: php artisan reverb:start');
        $this->components->warn('Rebuild the frontend (npm run build) so it picks up the new key.');

        return self::SUCCESS;
    }

    private function set(string $env, string $key, string $value): string
    {
        $line = "{$key}={$value}";

        return preg_match("/^{$key}=.*$/m", $env)
            ? preg_replace("/^{$key}=.*$/m", $line, $env)
            : rtrim($env)."\n{$line}\n";
    }
}
