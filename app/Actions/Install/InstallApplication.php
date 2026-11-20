<?php

namespace App\Actions\Install;

use App\Enums\UserRole;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Installer\DatabaseCredentials;
use App\Support\Installer\EnvironmentFile;
use App\Support\Installer\Installation;
use App\Support\Settings;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\DiningTableSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Sets up a fresh copy of the app on a server: database tables, the shop's
 * own details, the first super admin, the .env file and the lock file.
 *
 * Safe to run again after a failure: migrations skip what already ran, and
 * the admin and settings are saved with updateOrCreate / put.
 */
class InstallApplication
{
    public function __construct(
        private readonly Installation $installation,
        private readonly EnvironmentFile $env,
    ) {}

    /**
     * @param  array{url: string, timezone: string, shop_name: string, tagline: ?string, address: string, phone: string, whatsapp: ?string, sample_menu: bool, admin_name: string, admin_email: string, admin_password: string}  $site
     * @return array{storage_link: bool}
     */
    public function handle(DatabaseCredentials $database, array $site): array
    {
        $database->connect();
        config(['app.timezone' => $site['timezone']]);
        date_default_timezone_set($site['timezone']);

        $this->run('migrate', ['--force' => true]);

        $this->saveShopSettings($site);

        if ($site['sample_menu']) {
            foreach ([MenuSeeder::class, DeliveryZoneSeeder::class, DiningTableSeeder::class] as $seeder) {
                $this->run('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        }

        $admin = User::query()->updateOrCreate(['email' => strtolower($site['admin_email'])], [
            'name' => $site['admin_name'],
            'password' => $site['admin_password'],
            'role' => UserRole::Admin,
            'outlet_id' => null,
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $linked = $this->linkStorage();

        $this->env->set([
            // The session cookie is named after APP_NAME unless fixed: keep the
            // current one, or renaming the shop would sign the installer out.
            'SESSION_COOKIE' => $this->env->get('SESSION_COOKIE') ?: config('session.cookie'),
            'APP_NAME' => $site['shop_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim($site['url'], '/'),
            'APP_TIMEZONE' => $site['timezone'],
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'daily',
            'LOG_LEVEL' => 'error',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database->host,
            'DB_PORT' => $database->port,
            'DB_DATABASE' => $database->database,
            'DB_USERNAME' => $database->username,
            'DB_PASSWORD' => $database->password,
            // Shared hosting: files for sessions and cache, the database for the
            // queue, which the scheduler (one cron job) works through.
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'QUEUE_WORK_FROM_SCHEDULER' => true,
            'SCHEDULER_CRON_EXPECTED' => true,
            // No websocket server on shared hosting: screens refresh by polling.
            'BROADCAST_CONNECTION' => 'log',
            'FILESYSTEM_DISK' => 'public',
            'FILESYSTEM_PUBLIC_IN_WEBROOT' => ! $linked,
        ]);

        $this->installation->markInstalled();

        // Settings were cached while the shop details were saved.
        Cache::flush();

        return ['storage_link' => $linked];
    }

    /**
     * Replace the sample shop's details with the owner's own. Online payment
     * stays off: no payment gateway is connected yet.
     *
     * @param  array<string, mixed>  $site
     */
    private function saveShopSettings(array $site): void
    {
        $settings = app(Settings::class);
        $settings->flush();

        $settings->put([
            'shop.name' => $site['shop_name'],
            'shop.tagline' => (string) ($site['tagline'] ?? ''),
            'brand.icon' => '🍽️',
            'brand.footer_text' => '',
            'brand.badge_text' => '',
            'payments.fpx_enabled' => false,
            'payments.card_enabled' => false,
            'payments.ewallet_enabled' => false,
            'payments.cash_enabled' => true,
            // With no online payment, guests must be able to pay at the counter.
            'payments.cash_requires_account' => false,
        ]);

        $settings->put([
            'shop.address' => $site['address'],
            'shop.phone' => $site['phone'],
            'shop.whatsapp' => (string) ($site['whatsapp'] ?? ''),
            'shop.maps_url' => '',
        ], Outlet::default());
    }

    /**
     * public/storage → storage/app/public, so uploaded photos can be served.
     * Some hosts switch symlink() off; then uploads are stored straight in
     * public/storage instead (FILESYSTEM_PUBLIC_IN_WEBROOT).
     */
    private function linkStorage(): bool
    {
        $link = public_path('storage');

        if (is_link($link)) {
            return true;
        }

        if (is_dir($link)) {
            return false;
        }

        try {
            if (function_exists('symlink') && @symlink(storage_path('app/public'), $link)) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to the folder.
        }

        @mkdir($link, 0755, true);

        return false;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function run(string $command, array $parameters): void
    {
        $status = Artisan::call($command, $parameters);

        if ($status !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: "The {$command} step failed.");
        }
    }
}
