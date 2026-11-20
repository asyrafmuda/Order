<?php

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Support\Installer\DatabaseCredentials;
use App\Support\Installer\EnvironmentFile;
use App\Support\Installer\Installation;
use App\Support\Installer\SchedulerHeartbeat;
use App\Support\PaymentOptions;
use App\Support\Settings;
use Dotenv\Dotenv;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pretend the app has not been installed: no lock file, and no adopting of
 * the (migrated) test database.
 */
function freshUpload(): void
{
    config(['installer.lock' => sys_get_temp_dir().'/not-installed-'.bin2hex(random_bytes(4)).'.json', 'installer.adopt_existing' => false]);
    app()->forgetInstance(Installation::class);
}

/** Write the lock file as an earlier version would have. */
function installedAt(string $version): void
{
    file_put_contents(config('installer.lock'), json_encode(['version' => $version, 'installed_at' => '2026-01-01T00:00:00+08:00']));
    app()->forgetInstance(Installation::class);
}

describe('before installation', function () {
    beforeEach(fn () => freshUpload());

    it('sends every page to the installer', function (string $path) {
        $this->get($path)->assertRedirect(route('install.welcome'));
    })->with(['/', '/login', '/admin', '/kitchen', '/t/sometoken']);

    it('answers machines with 503 instead of a redirect', function () {
        $this->postJson('/webhooks/loyverse', [])->assertStatus(503)->assertHeader('Retry-After');
    });

    it('checks the server first', function () {
        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Order system setup')
            ->assertSee('PHP 8.3.0 or newer')
            ->assertSee('pdo_mysql')
            ->assertSee('storage/framework is writable');
    });

    it('explains a database that cannot be reached', function () {
        $this->post(route('install.database.store'), ['host' => '127.0.0.1', 'port' => 1, 'database' => 'shop', 'username' => 'shop', 'password' => 'x'])
            ->assertSessionHasErrors(['database' => 'Could not reach the database server. On most shared hosting the host is "localhost".']);

        expect(session('install.database'))->toBeNull();
    });

    it('asks for the database before the shop details', function () {
        $this->get(route('install.shop'))->assertRedirect(route('install.database'));
        $this->post(route('install.run'), [])->assertRedirect(route('install.database'))->assertSessionHasErrors('database');
    });

    it('keeps the installer away from the database it does not have', function () {
        // No session or cache tables exist yet: both must use files.
        $this->get(route('install.welcome'));

        expect(config('session.driver'))->toBe('file')->and(config('cache.default'))->toBe('file');
    });
});

describe('after installation', function () {
    it('closes the installer for good', function (string $route) {
        $this->get(route($route))->assertNotFound();
    })->with(['install.welcome', 'install.database', 'install.shop']);

    it('does not run an install over a working shop', function () {
        $this->post(route('install.database.store'), ['host' => 'localhost', 'port' => 3306, 'database' => 'x', 'username' => 'x'])->assertNotFound();
        $this->post(route('install.run'), [])->assertNotFound();
    });

    it('sends the update screen and finished page away when there is nothing to do', function () {
        $this->get(route('install.update'))->assertRedirect('/');
        $this->get(route('install.done'))->assertRedirect('/');
    });

    it('records an existing site as installed instead of showing the installer', function () {
        config(['installer.lock' => sys_get_temp_dir().'/adopt-'.bin2hex(random_bytes(4)).'.json', 'installer.adopt_existing' => true]);
        app()->forgetInstance(Installation::class);

        $this->get('/')->assertOk();

        $lock = json_decode((string) file_get_contents(config('installer.lock')), true);
        expect($lock['version'])->toBe(app(Installation::class)->version())->and($lock['via'])->toBe('existing');
    });
});

describe('updating', function () {
    beforeEach(function () {
        $this->admin = userWithRole(UserRole::Admin);

        // The new files bring a database change that has not run yet.
        Schema::drop('outlet_prices');
        DB::table('migrations')->where('migration', '2026_01_05_000100_create_outlet_prices')->delete();
        installedAt('0.9.0');
    });

    it('holds every page until the update has run', function () {
        $this->get('/')->assertRedirect(route('install.update'));
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('install.update'));

        $this->get(route('install.update'))->assertOk()
            ->assertSee('Finish the update to version '.app(Installation::class)->version())
            ->assertSee('was version 0.9.0')
            ->assertSee('1 database change');
    });

    it('runs the database changes once a super admin confirms', function () {
        $this->post(route('install.update.run'), ['email' => $this->admin->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHas('success');

        expect(Schema::hasTable('outlet_prices'))->toBeTrue()
            ->and(app(Installation::class)->installedVersion())->toBe(app(Installation::class)->version());

        $this->get('/')->assertOk();
    });

    it('refuses anyone but a super admin', function () {
        $cashier = userWithRole(UserRole::Cashier);

        $this->post(route('install.update.run'), ['email' => $cashier->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('install.update.run'), ['email' => $this->admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        expect(Schema::hasTable('outlet_prices'))->toBeFalse();
    });

    it('records a new version that brings no database changes without asking', function () {
        $this->artisan('migrate', ['--force' => true]);
        installedAt('0.9.0');

        $this->get('/')->assertOk();

        expect(app(Installation::class)->installedVersion())->toBe(app(Installation::class)->version());
    });

    it('is brought up to date by php artisan migrate as well', function () {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        app()->forgetInstance(Installation::class);
        expect(app(Installation::class)->installedVersion())->toBe(app(Installation::class)->version());
    });
});

describe('a live site without a payment gateway', function () {
    it('switches online payment off while only the fake gateway is set up', function () {
        app()->detectEnvironment(fn () => 'production');
        $options = app(PaymentOptions::class);

        expect($options->onlineReady())->toBeFalse()
            ->and($options->unavailableReason(PaymentMethod::Fpx, OrderType::Pickup, null, 1000))->toBe('Online payment is not set up yet')
            ->and($options->unavailableReason(PaymentMethod::Cash, OrderType::Pickup, userWithRole(UserRole::Customer), 1000))->toBeNull();

        config(['payment.fake_in_production' => true]);
        expect($options->unavailableReason(PaymentMethod::Fpx, OrderType::Pickup, null, 1000))->toBeNull();
    });

    it('keeps the fake gateway working for local demos and tests', function () {
        expect(app(PaymentOptions::class)->onlineReady())->toBeTrue();
    });
});

describe('the cron job warning', function () {
    beforeEach(fn () => $this->actingAs(userWithRole(UserRole::Admin)));

    it('warns super admins when scheduled work has stopped', function () {
        config(['installer.cron_expected' => true]);

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cronMissing.last_run', null)
            ->where('cronMissing.command', fn ($command) => str_contains($command, 'artisan schedule:run')));

        app(SchedulerHeartbeat::class)->beat();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('cronMissing', null));

        $this->travel(10)->minutes();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->whereNot('cronMissing', null));
    });

    it('stays quiet where no cron job is expected, and for managers', function () {
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('cronMissing', null));

        config(['installer.cron_expected' => true]);
        $this->actingAs(userWithRole(UserRole::Manager))->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('cronMissing', null));
    });

    it('works through the queue from the scheduler only when set up for it', function () {
        $queued = fn () => collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'queue:work');

        expect($queued()->filtersPass(app()))->toBeFalse();

        config(['installer.queue_from_scheduler' => true]);
        expect($queued()->filtersPass(app()))->toBeTrue();
    });

    it('runs every scheduled task without starting another process', function () {
        $events = collect(app(Schedule::class)->events());

        // A command string means a separate process (proc_open), which shared hosts often forbid.
        expect($events->pluck('command')->filter()->all())->toBe([])
            ->and($events->pluck('description')->all())->toContain('orders:cancel-unpaid', 'orders:close-stale', 'queue:work', 'scheduler-heartbeat');
    });
});

/*
| A real install, end to end, against MySQL. Runs only where a throwaway
| database is offered: INSTALLER_TEST_DB=name INSTALLER_TEST_USER=… INSTALLER_TEST_PASSWORD=…
*/
it('installs a working shop into an empty MySQL database', function () {
    freshUpload();
    $env = new EnvironmentFile(sys_get_temp_dir().'/installer-env-'.bin2hex(random_bytes(4)));
    $env->write("APP_KEY=base64:dGVzdA==\nAPP_ENV=production\n");
    app()->instance(EnvironmentFile::class, $env);

    $database = [
        'host' => env('INSTALLER_TEST_HOST', '127.0.0.1'), 'port' => 3306, 'database' => env('INSTALLER_TEST_DB'),
        'username' => env('INSTALLER_TEST_USER'), 'password' => env('INSTALLER_TEST_PASSWORD', ''),
    ];

    // Start from an empty database.
    (new DatabaseCredentials(...[...$database, 'port' => 3306]))->connect()->getSchemaBuilder()->dropAllTables();

    $this->post(route('install.database.store'), $database)->assertSessionHasNoErrors()->assertRedirect(route('install.shop'));

    $this->post(route('install.run'), [
        'url' => 'https://order.example.com', 'timezone' => 'Asia/Kuala_Lumpur',
        'shop_name' => 'Kedai "Ali" & Co', 'tagline' => 'Burger bakar', 'address' => '1 Jalan Besar, Shah Alam', 'phone' => '03-1234 5678', 'whatsapp' => '60312345678',
        'sample_menu' => '1',
        'admin_name' => 'Ali', 'admin_email' => 'Ali@Example.com', 'admin_password' => 'secret-pass-1', 'admin_password_confirmation' => 'secret-pass-1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('install.done'));

    // The settings file has everything the live site needs.
    $saved = Dotenv::parse((string) file_get_contents((fn () => $this->path)->call($env)));
    expect($saved)->toMatchArray([
        'APP_NAME' => 'Kedai "Ali" & Co', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://order.example.com',
        'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'],
        'SESSION_COOKIE' => config('session.cookie'), 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'database', 'QUEUE_WORK_FROM_SCHEDULER' => 'true',
        'BROADCAST_CONNECTION' => 'log',
    ]);

    // The shop, its admin and its menu are in the database.
    $admin = User::query()->where('email', 'ali@example.com')->sole();
    $settings = app(Settings::class);
    $settings->flush();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and(Hash::check('secret-pass-1', $admin->password))->toBeTrue()
        ->and($settings->global('shop.name'))->toBe('Kedai "Ali" & Co')
        ->and($settings->global('payments.fpx_enabled'))->toBeFalse()
        ->and($settings->forOutlet(Outlet::default())['shop.address'])->toBe('1 Jalan Besar, Shah Alam')
        ->and(Product::query()->count())->toBeGreaterThan(10)
        ->and(User::query()->count())->toBe(1)
        ->and(app(Installation::class)->isInstalled())->toBeTrue();

    $this->get(route('install.done'))->assertOk()->assertSee('is online')->assertSee('schedule:run');
    $this->get(route('install.welcome'))->assertNotFound();

    // Hand the test database back to RefreshDatabase.
    config(['database.default' => 'sqlite']);
    DB::setDefaultConnection('sqlite');
})->skip(fn () => ! env('INSTALLER_TEST_DB'), 'Set INSTALLER_TEST_DB to a throwaway MySQL database to run the full install.');
