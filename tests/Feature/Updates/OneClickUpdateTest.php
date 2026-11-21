<?php

use App\Enums\UserRole;
use App\Support\Installer\Installation;
use App\Support\Licensing\Certificate;
use App\Support\Licensing\License;
use App\Support\Updates\Updater;
use App\Support\Updates\UpdateServer;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const UPDATE_SITE = 'http://shop.example.com';

/**
 * A site installed from a release: its files and their file list, in a temp folder.
 *
 * @param  array<string, string>  $files
 */
function installedSite(string $version, array $files): string
{
    $root = sys_get_temp_dir().'/site-'.bin2hex(random_bytes(5));
    $zip = new ZipArchive;
    $zip->open(releaseZip($version, $files));
    $zip->extractTo($root);
    $zip->close();

    config(['installer.updates.root' => $root, 'installer.version_file' => $root.'/VERSION']);
    app(Installation::class)->markInstalled('tests', $version);

    return $root;
}

/**
 * The license server offering $zip as $version.
 */
function offerUpdate(string $version, string $zip, ?string $privateKey = null): void
{
    $manifest = Certificate::sign(['type' => 'release', 'product' => 'order-system', 'version' => $version, 'sha256' => hash_file('sha256', $zip), 'size' => filesize($zip)], $privateKey ?? licenseKeys()['private']);

    Http::fake([
        'licenses.seller.com/api/license/updates' => Http::response(['update' => [
            'version' => $version, 'notes' => 'Faster checkout', 'size' => filesize($zip), 'published_at' => now()->toIso8601String(),
            'download_url' => 'https://licenses.seller.com/api/license/download/1/1?signature=abc', 'manifest' => $manifest,
        ], 'locked' => null]),
        'licenses.seller.com/api/license/download/*' => Http::response((string) file_get_contents($zip), 200, ['Content-Type' => 'application/zip']),
    ]);
}

function siteFile(string $root, string $path): ?string
{
    return is_file("{$root}/{$path}") ? (string) file_get_contents("{$root}/{$path}") : null;
}

beforeEach(function () {
    enableLicensing();
    storeLicense();
    $this->actingAs(userWithRole(UserRole::Admin));

    $this->root = installedSite('1.0.0', [
        'app/Kept.php' => '<?php // kept',
        'app/Changed.php' => '<?php // v1',
        'app/Removed.php' => '<?php // gone in v2',
        '.htaccess' => '# v1 rules',
    ]);

    $this->zip = releaseZip('1.1.0', [
        'app/Kept.php' => '<?php // kept',
        'app/Changed.php' => '<?php // v2',
        'app/Added.php' => '<?php // new in v2',
        '.htaccess' => '# v1 rules',
    ]);
});

it('checks for updates and shows what is new', function () {
    offerUpdate('1.1.0', $this->zip);

    $this->post(UPDATE_SITE.'/admin/system-update/check')->assertSessionHas('success', 'Version 1.1.0 is available.');

    $this->get(UPDATE_SITE.'/admin/system-update')->assertInertia(fn (Assert $page) => $page
        ->component('Admin/SystemUpdate/Show')
        ->where('current', '1.0.0')
        ->where('update.version', '1.1.0')
        ->where('update.notes', 'Faster checkout')
        ->where('blockers', [])
        ->where('auth.user.licensing.update', '1.1.0'));
});

it('updates with one click: replaces, adds and removes files, then finishes on the new code', function () {
    offerUpdate('1.1.0', $this->zip);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $response = $this->post(UPDATE_SITE.'/admin/system-update/install', [], ['X-Inertia' => 'true'])->assertStatus(409);
    $finish = (string) $response->headers->get('X-Inertia-Location');

    expect($finish)->toContain('/install/update/finish/')
        ->and(siteFile($this->root, 'app/Changed.php'))->toBe('<?php // v2')
        ->and(siteFile($this->root, 'app/Added.php'))->toBe('<?php // new in v2')
        ->and(siteFile($this->root, 'app/Removed.php'))->toBeNull()
        ->and(siteFile($this->root, 'VERSION'))->toBe("1.1.0\n")
        ->and(json_decode(siteFile($this->root, Updater::MANIFEST), true)['version'])->toBe('1.1.0')
        ->and(Updater::applying())->toBeTrue();

    // Customers wait while the files are replaced; the finishing request goes through.
    $this->get(UPDATE_SITE.'/')->assertStatus(503)->assertSee('Updating');

    $this->get($finish)->assertRedirect('/admin/system-update')->assertSessionHas('success', 'Updated to version 1.1.0.');

    expect(Updater::applying())->toBeFalse()
        ->and(app(Installation::class)->installedVersion())->toBe('1.1.0')
        ->and(app(UpdateServer::class)->last()['update'])->toBeNull()
        ->and(glob(Updater::workDir().'/release-*.zip'))->toBe([]);

    // The backup holds the old versions of everything that changed.
    $backup = new ZipArchive;
    $backup->open(Updater::workDir().'/backups/'.app(Updater::class)->backups()[0]['name']);
    expect($backup->getFromName('app/Changed.php'))->toBe('<?php // v1')
        ->and($backup->getFromName('app/Removed.php'))->toBe('<?php // gone in v2')
        ->and(json_decode($backup->getFromName('.update-added.json'), true))->toContain('app/Added.php');

    // The link works once.
    $this->get($finish)->assertSessionHas('error');
});

it('lists files the owner changed and only replaces them when told to', function () {
    file_put_contents($this->root.'/app/Changed.php', '<?php // my own fix');
    file_put_contents($this->root.'/app/Removed.php', '<?php // my own edit');
    offerUpdate('1.1.0', $this->zip);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $this->post(UPDATE_SITE.'/admin/system-update/install')->assertSessionHas('error');

    expect(siteFile($this->root, 'app/Changed.php'))->toBe('<?php // my own fix')
        ->and(siteFile($this->root, 'VERSION'))->toBe("1.0.0\n");
    $this->get(UPDATE_SITE.'/admin/system-update')->assertInertia(fn (Assert $page) => $page
        ->where('plan.changed', ['app/Changed.php', 'app/Removed.php'])
        ->where('plan.changed_count', 2));

    $this->post(UPDATE_SITE.'/admin/system-update/install', ['replace_changed' => true], ['X-Inertia' => 'true'])->assertStatus(409);

    expect(siteFile($this->root, 'app/Changed.php'))->toBe('<?php // v2');
    $backup = new ZipArchive;
    $backup->open(Updater::workDir().'/backups/'.app(Updater::class)->backups()[0]['name']);
    expect($backup->getFromName('app/Changed.php'))->toBe('<?php // my own fix');
});

it('does not list a file the owner changed when the update leaves it alone', function () {
    file_put_contents($this->root.'/.htaccess', '# my https redirect');
    offerUpdate('1.1.0', $this->zip);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $this->post(UPDATE_SITE.'/admin/system-update/install', [], ['X-Inertia' => 'true'])->assertStatus(409);

    expect(siteFile($this->root, '.htaccess'))->toBe('# my https redirect');
});

it('refuses an update not signed by the seller', function () {
    $other = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($other, $otherKey);
    offerUpdate('1.1.0', $this->zip, $otherKey);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $this->post(UPDATE_SITE.'/admin/system-update/install')->assertSessionHas('error', 'The update is not signed by the seller, so it was not installed.');

    expect(siteFile($this->root, 'VERSION'))->toBe("1.0.0\n");
});

it('throws away a download that is not the ZIP the seller signed', function () {
    $tampered = releaseZip('1.1.0', ['app/Changed.php' => '<?php evil();']);
    $manifest = Certificate::sign(['type' => 'release', 'product' => 'order-system', 'version' => '1.1.0', 'sha256' => hash_file('sha256', $this->zip), 'size' => filesize($this->zip)], licenseKeys()['private']);
    Http::fake([
        'licenses.seller.com/api/license/updates' => Http::response(['update' => ['version' => '1.1.0', 'size' => filesize($this->zip), 'download_url' => 'https://licenses.seller.com/dl/x', 'manifest' => $manifest], 'locked' => null]),
        'licenses.seller.com/dl/*' => Http::response((string) file_get_contents($tampered)),
    ]);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $this->post(UPDATE_SITE.'/admin/system-update/install')->assertSessionHas('error', fn ($message) => str_contains($message, 'does not match'));

    expect(siteFile($this->root, 'app/Changed.php'))->toBe('<?php // v1')
        ->and(siteFile($this->root, 'VERSION'))->toBe("1.0.0\n")
        ->and(glob(Updater::workDir().'/release-*.zip'))->toBe([]);
});

it('puts everything back when installing fails half way', function () {
    // app/Added.php cannot be written: a folder is in the way.
    mkdir($this->root.'/app/Added.php');
    offerUpdate('1.1.0', $this->zip);
    $this->post(UPDATE_SITE.'/admin/system-update/check');

    $this->post(UPDATE_SITE.'/admin/system-update/install')->assertSessionHas('error', fn ($message) => str_contains($message, 'put back as it was'));

    expect(siteFile($this->root, 'app/Changed.php'))->toBe('<?php // v1')
        ->and(siteFile($this->root, 'app/Removed.php'))->toBe('<?php // gone in v2')
        ->and(siteFile($this->root, 'VERSION'))->toBe("1.0.0\n")
        ->and(json_decode(siteFile($this->root, Updater::MANIFEST), true)['version'])->toBe('1.0.0')
        ->and(Updater::applying())->toBeFalse();
    $this->get(UPDATE_SITE.'/')->assertOk();
});

it('shows when the update period has ended', function () {
    Http::fake(['licenses.seller.com/api/license/updates' => Http::response(['update' => null, 'locked' => ['version' => '2.0.0', 'updates_until' => '2026-06-30']])]);

    $this->post(UPDATE_SITE.'/admin/system-update/check')->assertSessionHas('success', fn ($message) => str_contains($message, 'update period has ended'));
    $this->get(UPDATE_SITE.'/admin/system-update')->assertInertia(fn (Assert $page) => $page->where('update', null)->where('locked.version', '2.0.0'));
});

it('never installs a version that is not newer', function () {
    offerUpdate('1.0.0', releaseZip('1.0.0'));

    $this->post(UPDATE_SITE.'/admin/system-update/check')->assertSessionHas('success', fn ($message) => str_contains($message, 'up to date'));
    $this->post(UPDATE_SITE.'/admin/system-update/install')->assertSessionHas('error', fn ($message) => str_contains($message, 'no update'));
});

it('will not touch a git checkout', function () {
    mkdir($this->root.'/.git');

    expect(app(Updater::class)->blockers())->toContain('This copy is a git checkout. Update it with git pull, then php artisan migrate.');
});

it('needs an activated license, and is for super admins only', function () {
    $this->actingAs(userWithRole(UserRole::Manager))->get(UPDATE_SITE.'/admin/system-update')->assertForbidden();

    @unlink(config('licensing.file'));
    app()->forgetInstance(License::class);
    expect(app(UpdateServer::class)->supported())->toBeFalse();
});

it('stops holding customers back if an update never finished', function () {
    @mkdir(Updater::workDir(), 0775, true);
    touch(Updater::workDir().'/applying', time() - 16 * 60);

    expect(Updater::applying())->toBeFalse();
});
