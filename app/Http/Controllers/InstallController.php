<?php

namespace App\Http\Controllers;

use App\Actions\Install\InstallApplication;
use App\Support\Installer\DatabaseCredentials;
use App\Support\Installer\EnvironmentFile;
use App\Support\Installer\Installation;
use App\Support\Installer\Requirements;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * The web installer: check the server, connect the database, describe the
 * shop and create the first super admin. Closed for good once it has run.
 */
class InstallController extends Controller
{
    public function __construct(private readonly Installation $installation) {}

    public function welcome(Requirements $requirements): View
    {
        $this->ensureNotInstalled();

        return view('install.welcome', [
            'checks' => collect($requirements->checks())->groupBy('group'),
            'passes' => $requirements->passes(),
            'version' => $this->installation->version(),
        ]);
    }

    public function database(Request $request, Requirements $requirements): View|RedirectResponse
    {
        $this->ensureNotInstalled();

        if (! $requirements->passes()) {
            return redirect()->route('install.welcome');
        }

        return view('install.database', [
            'values' => [...['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => ''], ...$request->session()->get('install.database', [])],
            'existing' => $request->session()->get('install.existing'),
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $this->ensureNotInstalled();

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $credentials = DatabaseCredentials::fromArray($validated);

        try {
            $found = $credentials->check();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['database' => $exception->getMessage()]);
        }

        $request->session()->put('install.database', $credentials->toArray());
        $request->session()->forget('install.existing');

        $state = $found['state'];

        if ($state === 'foreign') {
            throw ValidationException::withMessages([
                'database' => 'This database already holds tables from another program. Create a new, empty database for this app and use that one.',
            ]);
        }

        if ($state === 'existing') {
            // The app was installed here before (a reinstall of the files): offer to reconnect.
            $request->session()->put('install.existing', $found['server']);

            return redirect()->route('install.database');
        }

        return redirect()->route('install.shop')->with('success', "Connected to {$found['server']}.");
    }

    /**
     * The files were uploaded again over a database that already has a shop
     * in it: keep everything and just write the settings file.
     */
    public function reconnect(Request $request, EnvironmentFile $env): RedirectResponse
    {
        $this->ensureNotInstalled();
        $credentials = $this->credentials($request);

        $validated = $request->validate([
            'url' => ['required', 'url:http,https', 'max:255'],
        ]);

        try {
            $credentials->connect();
            $env->set([
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
                'APP_URL' => rtrim($validated['url'], '/'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $credentials->host,
                'DB_PORT' => $credentials->port,
                'DB_DATABASE' => $credentials->database,
                'DB_USERNAME' => $credentials->username,
                'DB_PASSWORD' => $credentials->password,
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'database',
                'QUEUE_WORK_FROM_SCHEDULER' => true,
                'SCHEDULER_CRON_EXPECTED' => true,
                'BROADCAST_CONNECTION' => 'log',
            ]);

            // Database changes the new files bring run on the update screen.
            $this->installation->markInstalled('reconnected', $this->installation->pendingMigrations() === [] ? null : '0.0.0');
        } catch (Throwable $exception) {
            Log::error('Reconnecting the installation failed', ['exception' => $exception]);

            throw ValidationException::withMessages(['database' => 'Could not save the settings: '.$exception->getMessage()]);
        }

        $request->session()->forget(['install.database', 'install.existing']);

        return redirect()->to('/login')->with('success', 'Reconnected to your existing shop. Sign in with your usual admin account.');
    }

    public function shop(Request $request): View|RedirectResponse
    {
        $this->ensureNotInstalled();

        if (! $request->session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        return view('install.shop', [
            'url' => rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'timezone' => config('app.timezone') ?: 'Asia/Kuala_Lumpur',
        ]);
    }

    public function install(Request $request, InstallApplication $install): RedirectResponse
    {
        $this->ensureNotInstalled();
        $credentials = $this->credentials($request);

        $site = $request->validate([
            'url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'shop_name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^[0-9]{9,15}$/'],
            'sample_menu' => ['boolean'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'whatsapp.regex' => 'Use digits only in international format, e.g. 60123456789.',
            'phone.regex' => 'Enter a valid phone number, e.g. 03-1234 5678.',
        ], [
            'admin_name' => 'your name',
            'admin_email' => 'email',
            'admin_password' => 'password',
        ]);

        @set_time_limit(300);

        try {
            $result = $install->handle($credentials, [...$site, 'sample_menu' => $request->boolean('sample_menu')]);
        } catch (Throwable $exception) {
            Log::error('Installation failed', ['exception' => $exception]);

            return back()->withInput($request->except('admin_password', 'admin_password_confirmation'))
                ->withErrors(['install' => 'Installation stopped: '.$exception->getMessage().' Fix the problem and press Install again — it continues where it stopped.']);
        }

        $request->session()->forget(['install.database', 'install.existing']);
        $request->session()->put('install.done', [
            'url' => rtrim($site['url'], '/'),
            'email' => strtolower($site['admin_email']),
            'shop' => $site['shop_name'],
            'storage_link' => $result['storage_link'],
            'sample_menu' => $request->boolean('sample_menu'),
        ]);

        return redirect()->route('install.done');
    }

    public function done(Request $request): View|RedirectResponse
    {
        $done = $request->session()->get('install.done');

        if (! $done || ! $this->installation->isInstalled()) {
            return redirect()->to('/');
        }

        return view('install.done', [
            'done' => $done,
            'cron' => $this->cronCommand(),
            'basePath' => base_path(),
        ]);
    }

    private function ensureNotInstalled(): void
    {
        abort_if($this->installation->isInstalled(), 404);
    }

    private function credentials(Request $request): DatabaseCredentials
    {
        $stored = $request->session()->get('install.database');

        if (! is_array($stored)) {
            throw ValidationException::withMessages(['database' => 'Enter the database details first.'])->redirectTo(route('install.database'));
        }

        return DatabaseCredentials::fromArray($stored);
    }

    /**
     * The cron job line for this server. Under PHP-FPM or LiteSpeed the
     * running binary is not the command-line PHP, so its sibling is suggested.
     */
    private function cronCommand(): string
    {
        $binary = PHP_BINARY;
        $guess = preg_replace(['#/sbin/php-fpm[0-9.]*$#', '#/(lsphp|php-cgi|php-fpm)[0-9.]*$#'], ['/bin/php', '/php'], $binary) ?: $binary;
        $php = $guess !== '' && @is_file($guess) ? $guess : '/usr/local/bin/php';

        // Without the five schedule fields: cPanel asks for those separately.
        return $php.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }
}
