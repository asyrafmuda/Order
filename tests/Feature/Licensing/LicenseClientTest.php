<?php

use App\Enums\UserRole;
use App\Support\Installer\Installation;
use App\Support\Licensing\Certificate;
use App\Support\Licensing\License;
use App\Support\Licensing\LicenseStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const SHOP = 'http://shop.example.com';

it('changes nothing while licensing is not set up', function () {
    expect(app(License::class)->configured())->toBeFalse()
        ->and(app(License::class)->status('shop.example.com'))->toBe(LicenseStatus::NotRequired);

    menuItem();
    $this->get(SHOP.'/')->assertOk();
});

describe('a licensed build', function () {
    beforeEach(fn () => enableLicensing());

    it('never asks for a license on local and private addresses', function (string $host) {
        expect(app(License::class)->status($host))->toBe(LicenseStatus::NotRequired);
    })->with(['localhost', '127.0.0.1', 'localhost:8000', 'kedai.test', 'shop.local', '192.168.1.20', '10.0.0.5']);

    it('asks on a public address', function (string $host) {
        expect(app(License::class)->status($host))->toBe(LicenseStatus::Missing);
    })->with(['shop.example.com', 'www.kedai.com.my', '203.0.113.10']);

    it('stops the site until it is activated, but lets the owner sign in and fix it', function () {
        $this->get(SHOP.'/')->assertStatus(503)->assertSee('Temporarily unavailable')->assertSee('Not activated');
        $this->postJson(SHOP.'/webhooks/loyverse', [])->assertStatus(503);

        $this->get(SHOP.'/login')->assertOk();
        $this->actingAs(userWithRole(UserRole::Cashier))->get(SHOP.'/counter')->assertStatus(503)->assertSee('Sign in to fix it');

        $this->actingAs(userWithRole(UserRole::Admin));
        $this->get(SHOP.'/admin')->assertRedirect(route('admin.license.show'));
        $this->get(SHOP.'/admin/license')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/License/Show')
            ->where('license.status', 'missing')
            ->where('license.usable', false)
            ->where('license.host', 'shop.example.com'));

        // Other staff cannot fix it and see the notice.
        $this->actingAs(userWithRole(UserRole::Manager))->get(SHOP.'/admin')->assertStatus(503);
    });

    it('activates a purchase code from the admin', function () {
        Http::fake(['licenses.seller.com/api/license/activate' => Http::response(signedLicense())]);
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->post(SHOP.'/admin/license', ['code' => ' abcd-efgh-jklm-npqr '])
            ->assertRedirect(route('admin.license.show'))
            ->assertSessionHas('success');

        Http::assertSent(fn (ClientRequest $request) => $request['code'] === 'ABCD-EFGH-JKLM-NPQR'
            && $request['domain'] === 'shop.example.com'
            && $request['product'] === 'order-system'
            && $request['version'] === app(Installation::class)->version());

        $this->get(SHOP.'/admin')->assertOk();
        $this->get(SHOP.'/admin/license')->assertInertia(fn (Assert $page) => $page
            ->where('license.status', 'valid')
            ->where('license.customer', 'Kedai Ali')
            ->where('license.code', '••••-••••-••••-NPQR'));

        auth()->logout();
        menuItem();
        $this->get(SHOP.'/')->assertOk();
    });

    it('shows why the server refused a code', function () {
        Http::fake(['*' => Http::response(['message' => 'This purchase code is already in use on other.com.'], 422)]);

        $this->actingAs(userWithRole(UserRole::Admin))
            ->post(SHOP.'/admin/license', ['code' => 'ABCD-EFGH-JKLM-NPQR'])
            ->assertSessionHasErrors(['code' => 'This purchase code is already in use on other.com.']);
    });

    it('rejects a certificate it cannot verify', function () {
        $forger = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($forger, $forgedKey);

        Http::fake(['*' => Http::response(signedLicense([], $forgedKey))]);

        $this->actingAs(userWithRole(UserRole::Admin))
            ->post(SHOP.'/admin/license', ['code' => 'ABCD-EFGH-JKLM-NPQR'])
            ->assertSessionHasErrors(['code' => 'The license server sent an answer that could not be verified.']);

        // And a file edited by hand is no license either.
        $signed = signedLicense();
        file_put_contents(config('licensing.file'), json_encode([...$signed, 'payload' => base64_encode(json_encode(['domain' => 'shop.example.com', 'status' => 'active']))]));
        app()->forgetInstance(License::class);

        expect(app(License::class)->status('shop.example.com'))->toBe(LicenseStatus::Invalid);
    });

    it('works only on the address it was activated for', function () {
        storeLicense(['domain' => 'shop.example.com']);
        $license = app(License::class);

        expect($license->status('shop.example.com'))->toBe(LicenseStatus::Valid)
            ->and($license->status('www.shop.example.com'))->toBe(LicenseStatus::Valid)
            ->and($license->status('SHOP.example.com:443'))->toBe(LicenseStatus::Valid)
            ->and($license->status('copy.example.com'))->toBe(LicenseStatus::WrongDomain);

        $this->get('http://copy.example.com/')->assertStatus(503)->assertSee('Activated for another domain');
    });

    it('stops when the seller cancels the license', function () {
        storeLicense(['checked_at' => now()->subDays(8)->toIso8601String()]);
        Http::fake(['*/check' => Http::response(signedLicense(['status' => 'revoked', 'message' => 'This license was cancelled by the seller: refund.']))]);

        $this->artisan('license:check')->assertFailed();

        expect(app(License::class)->status('shop.example.com'))->toBe(LicenseStatus::Revoked);

        $this->get(SHOP.'/')->assertStatus(503)->assertSee('Cancelled');
        $this->actingAs(userWithRole(UserRole::Admin))->get(SHOP.'/admin/license')
            ->assertInertia(fn (Assert $page) => $page->where('license.message', 'This license was cancelled by the seller: refund.'));
    });

    it('keeps working while the license server is down, then warns, then stops', function () {
        storeLicense();
        Http::fake(fn () => throw new ConnectionException('down'));
        $license = fn () => app(License::class)->status('shop.example.com');

        $this->travel(8)->days();
        expect($license())->toBe(LicenseStatus::Stale);
        $this->artisan('license:check')->assertFailed();
        expect($license())->toBe(LicenseStatus::Stale);

        $this->travel(7)->days();
        expect($license())->toBe(LicenseStatus::Warning);
        $this->actingAs(userWithRole(UserRole::Admin))->get(SHOP.'/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('licenseWarning', fn ($text) => str_contains($text, 'license server has not answered')));

        $this->travel(16)->days();
        expect($license())->toBe(LicenseStatus::Expired);
        auth()->logout();
        $this->get(SHOP.'/')->assertStatus(503)->assertSee('Could not be confirmed');
    });

    it('fetches a fresh certificate in the background when one is due', function () {
        storeLicense(['checked_at' => now()->subDays(8)->toIso8601String()]);
        Http::fake(['*/check' => Http::response(signedLicense())]);
        menuItem();

        $this->get(SHOP.'/')->assertOk();

        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/api/license/check'));
        app()->forgetInstance(License::class);
        expect(app(License::class)->status('shop.example.com'))->toBe(LicenseStatus::Valid);

        // At most once an hour.
        $this->get(SHOP.'/');
        Http::assertSentCount(1);
    });

    it('releases the domain before a move', function () {
        storeLicense();
        Http::fake(['*/deactivate' => Http::response(['message' => 'Released.'])]);

        $this->actingAs(userWithRole(UserRole::Admin))->delete(SHOP.'/admin/license')->assertSessionHas('success');

        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/deactivate') && $request['domain'] === 'shop.example.com');
        expect(file_exists(config('licensing.file')))->toBeFalse()
            ->and(app(License::class)->status('shop.example.com'))->toBe(LicenseStatus::Missing);
    });

    it('adds a license step to the installer', function () {
        config(['installer.lock' => sys_get_temp_dir().'/not-installed-'.bin2hex(random_bytes(4)).'.json', 'installer.adopt_existing' => false]);
        app()->forgetInstance(Installation::class);
        Http::fake(['*/activate' => Http::response(signedLicense())]);

        $this->get(SHOP.'/install/database')->assertRedirect(route('install.license'));
        $this->get(SHOP.'/install/license')->assertOk()->assertSee('Activate your license')->assertSee('shop.example.com');

        $this->post(SHOP.'/install/license', ['code' => 'ABCD-EFGH-JKLM-NPQR'])
            ->assertRedirect(route('install.database'))
            ->assertSessionHas('success', 'License activated for shop.example.com.');

        $this->get(SHOP.'/install/database')->assertOk();
        $this->get(SHOP.'/install/license')->assertRedirect(route('install.database'));

        // Installing on a local address skips it.
        app()->forgetInstance(License::class);
        @unlink(config('licensing.file'));
        $this->get('http://localhost/install/license')->assertRedirect(route('install.database'));
    });

    it('shows the license screens only to super admins of a licensed build', function () {
        $this->actingAs(userWithRole(UserRole::Admin))->get('http://localhost/admin')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.licensing', ['client' => true, 'server' => false, 'update' => null]));

        $this->actingAs(userWithRole(UserRole::Manager))->get('http://localhost/admin')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.licensing', null));
        $this->get('http://localhost/admin/license')->assertForbidden();
    });
});

it('signs and verifies certificates, and notices any change', function () {
    $signed = signedLicense();
    $public = licenseKeys()['public'];

    expect(Certificate::verify($signed['payload'], $signed['signature'], $public)?->domain())->toBe('shop.example.com')
        ->and(Certificate::verify($signed['payload'].'x', $signed['signature'], $public))->toBeNull()
        ->and(Certificate::verify($signed['payload'], 'garbage', $public))->toBeNull()
        ->and(Certificate::verify($signed['payload'], $signed['signature'], 'not a key'))->toBeNull();
});
