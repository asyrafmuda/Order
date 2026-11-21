<?php

use App\Enums\UserRole;
use App\LicenseServer\Models\License;
use App\LicenseServer\Models\Release;
use App\LicenseServer\ReleasePackage;
use App\Support\Licensing\Certificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    config([
        'licensing.server.enabled' => true,
        'licensing.server.signing_key' => base64_encode(licenseKeys()['private']),
        'licensing.public_key' => licenseKeys()['public'],
    ]);

    $this->license = License::query()->create(['code' => License::newCode(), 'product' => 'order-system', 'customer_name' => 'Kedai Ali', 'max_activations' => 1]);
    $this->activation = $this->license->activations()->create(['domain' => 'shop.example.com', 'activated_at' => now()]);
});

function publishRelease(string $version, array $attributes = []): Release
{
    $release = app(ReleasePackage::class)->store(releaseZip($version), "Notes for {$version}", true);
    $release->update($attributes);

    return $release->refresh();
}

function askForUpdates(License $license, string $version = '1.0.0', string $domain = 'shop.example.com')
{
    return test()->postJson('/api/license/updates', ['code' => $license->code, 'domain' => $domain, 'product' => 'order-system', 'version' => $version]);
}

describe('uploading a release', function () {
    it('accepts the ZIP from package.sh and publishes it', function () {
        $this->actingAs(userWithRole(UserRole::Admin));
        $zip = releaseZip('1.1.0');

        $this->post(route('admin.releases.store'), [
            'file' => new UploadedFile($zip, 'order-system-1.1.0.zip', 'application/zip', null, true),
            'notes' => 'Payment gateways',
            'publish' => true,
            'audience' => 'all',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $release = Release::query()->sole();
        expect($release->version)->toBe('1.1.0')
            ->and($release->isPublished())->toBeTrue()
            ->and($release->sha256)->toBe(hash_file('sha256', $zip))
            ->and(Storage::disk('local')->exists($release->path))->toBeTrue();

        $this->get(route('admin.releases.index'))->assertInertia(fn (Assert $page) => $page->component('Admin/Releases/Index')->has('releases', 1)->where('versions', ['unknown' => 1]));
    });

    it('refuses ZIPs that would hurt every site installing them', function (array $files, array $options, string $message) {
        expect(fn () => app(ReleasePackage::class)->store(releaseZip('1.1.0', $files, $options), null, true))->toThrow(RuntimeException::class, $message);
        expect(Release::query()->count())->toBe(0);
    })->with([
        'no file list' => [[], ['manifest' => false], 'no file list'],
        'built without the license check' => [[], ['public_key' => ''], 'without the license check'],
        'another key' => [[], ['public_key' => "-----BEGIN PUBLIC KEY-----\nOTHER\n-----END PUBLIC KEY-----\n"], 'different license key'],
        'contains the license server' => [['app/LicenseServer/Signer.php' => '<?php'], [], 'contains the license server'],
    ]);

    it('refuses the same version twice and a non-ZIP', function () {
        publishRelease('1.1.0');

        expect(fn () => app(ReleasePackage::class)->store(releaseZip('1.1.0'), null, true))->toThrow(RuntimeException::class, 'uploaded already');

        $text = tempnam(sys_get_temp_dir(), 'nozip');
        file_put_contents($text, 'hello');
        expect(fn () => app(ReleasePackage::class)->inspect($text))->toThrow(RuntimeException::class, 'not a ZIP');
    });

    it('can be published from the command line', function () {
        $this->artisan('release:publish', ['zip' => releaseZip('1.2.0'), '--notes' => 'Faster'])->assertSuccessful();

        expect(Release::query()->sole())->version->toBe('1.2.0')->notes->toBe('Faster');
    });

    it('is only for super admins of the license server', function () {
        $this->actingAs(userWithRole(UserRole::Manager))->get(route('admin.releases.index'))->assertForbidden();

        config(['licensing.server.enabled' => false]);
        $this->actingAs(userWithRole(UserRole::Admin))->get('/admin/releases')->assertNotFound();
    });
});

describe('offering updates', function () {
    it('offers the newest published release with a signed checksum and a download link', function () {
        publishRelease('1.1.0');
        $latest = publishRelease('1.2.0');
        publishRelease('1.3.0', ['published_at' => null]); // a draft

        $response = askForUpdates($this->license)->assertOk()->assertJsonPath('update.version', '1.2.0')->assertJsonPath('locked', null);

        $manifest = Certificate::verify($response->json('update.manifest.payload'), $response->json('update.manifest.signature'), licenseKeys()['public']);
        expect($manifest->get('type'))->toBe('release')
            ->and($manifest->get('version'))->toBe('1.2.0')
            ->and($manifest->get('sha256'))->toBe($latest->sha256)
            ->and($this->activation->refresh()->version)->toBe('1.0.0');

        $this->get($response->json('update.download_url'))->assertOk()->assertDownload('order-system-1.2.0.zip');
    });

    it('offers nothing when the site is up to date', function () {
        publishRelease('1.1.0');

        askForUpdates($this->license, '1.1.0')->assertOk()->assertJsonPath('update', null);
    });

    it('offers a release for picked customers only to them', function () {
        $release = publishRelease('1.1.0', ['audience' => Release::AUDIENCE_SELECTED]);
        $other = License::query()->create(['code' => License::newCode(), 'product' => 'order-system', 'customer_name' => 'Kedai Abu', 'max_activations' => 1]);
        $other->activations()->create(['domain' => 'abu.example.com', 'activated_at' => now()]);
        $release->licenses()->sync([$this->license->id]);

        askForUpdates($this->license)->assertJsonPath('update.version', '1.1.0');
        askForUpdates($other, domain: 'abu.example.com')->assertJsonPath('update', null);
    });

    it('stops offering releases published after the update period', function () {
        $this->license->update(['updates_until' => '2026-06-30']);
        $this->travelTo('2026-06-01');
        publishRelease('1.1.0');
        $this->travelTo('2026-08-01');
        publishRelease('1.2.0');

        askForUpdates($this->license)->assertJsonPath('update.version', '1.1.0')->assertJsonPath('locked.version', '1.2.0')->assertJsonPath('locked.updates_until', '2026-06-30');
        askForUpdates($this->license, '1.1.0')->assertJsonPath('update', null)->assertJsonPath('locked.version', '1.2.0');
    });

    it('refuses cancelled or unactivated codes', function () {
        publishRelease('1.1.0');

        askForUpdates($this->license, domain: 'elsewhere.example.com')->assertStatus(422);

        $this->license->update(['revoked_at' => now()]);
        askForUpdates($this->license)->assertStatus(422);
    });

    it('only lets the signed link download, and not after the code is cancelled', function () {
        $release = publishRelease('1.1.0');
        $url = askForUpdates($this->license)->json('update.download_url');

        $this->get("/api/license/download/{$release->id}/{$this->activation->id}")->assertForbidden();
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        $this->license->update(['revoked_at' => now()]);
        $this->get($url)->assertForbidden();
    });

    it('saves the update period on a license', function () {
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.licenses.update', $this->license), ['customer_name' => 'Kedai Ali', 'max_activations' => 1, 'updates_until' => '2027-03-31'])->assertSessionHasNoErrors();

        expect($this->license->refresh()->updates_until->toDateString())->toBe('2027-03-31');
    });

    it('edits who gets a release and unpublishes it', function () {
        $release = publishRelease('1.1.0');
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.releases.update', $release), ['notes' => 'x', 'publish' => true, 'audience' => 'selected'])->assertSessionHasErrors('license_ids');
        $this->put(route('admin.releases.update', $release), ['notes' => 'x', 'publish' => false, 'audience' => 'all', 'license_ids' => []])->assertSessionHasNoErrors();

        expect($release->refresh()->isPublished())->toBeFalse();
        askForUpdates($this->license)->assertJsonPath('update', null);

        $this->delete(route('admin.releases.destroy', $release))->assertSessionHas('success');
        expect(Release::query()->count())->toBe(0)->and(Storage::disk('local')->exists($release->path))->toBeFalse();
    });
});
