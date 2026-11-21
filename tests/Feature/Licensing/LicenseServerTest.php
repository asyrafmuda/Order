<?php

use App\Enums\UserRole;
use App\LicenseServer\Models\Activation;
use App\LicenseServer\Models\License;
use App\Support\Licensing\Certificate;
use App\Support\Licensing\License as ClientLicense;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'licensing.server.enabled' => true,
        'licensing.server.signing_key' => base64_encode(licenseKeys()['private']),
        'licensing.public_key' => licenseKeys()['public'],
    ]);

    $this->license = License::query()->create([
        'code' => License::newCode(), 'product' => 'order-system', 'customer_name' => 'Kedai Ali', 'max_activations' => 1,
    ]);
});

function callServer(string $action, array $data): TestResponse
{
    return test()->postJson('/api/license/'.$action, [...['product' => 'order-system', 'version' => '1.0.0'], ...$data]);
}

function certificateFrom(TestResponse $response): Certificate
{
    $certificate = Certificate::verify((string) $response->json('payload'), (string) $response->json('signature'), licenseKeys()['public']);
    expect($certificate)->not->toBeNull();

    return $certificate;
}

it('makes purchase codes that are easy to read out', function () {
    expect($this->license->code)->toMatch('/^[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){3}$/');
});

it('activates a code for a domain and signs the answer', function () {
    $response = callServer('activate', ['code' => strtolower($this->license->code), 'domain' => 'WWW.Shop.Example.com:443'])->assertOk();
    $certificate = certificateFrom($response);

    expect($certificate->domain())->toBe('shop.example.com')
        ->and($certificate->get('status'))->toBe('active')
        ->and($certificate->get('customer'))->toBe('Kedai Ali')
        ->and($certificate->get('code'))->toBe($this->license->code);

    $activation = Activation::query()->sole();
    expect($activation->domain)->toBe('shop.example.com')->and($activation->version)->toBe('1.0.0');
});

it('treats installing again on the same domain as the same activation', function () {
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertOk();
    callServer('activate', ['code' => $this->license->code, 'domain' => 'www.shop.example.com'])->assertOk();

    expect(Activation::query()->count())->toBe(1);
});

it('refuses a second domain once the limit is reached', function () {
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertOk();

    callServer('activate', ['code' => $this->license->code, 'domain' => 'copy.example.com'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This purchase code is already in use on shop.example.com. Release it there first (Admin → License → Move to another domain), or ask the seller to reset it.');

    $this->license->update(['max_activations' => 2]);
    callServer('activate', ['code' => $this->license->code, 'domain' => 'copy.example.com'])->assertOk();
});

it('refuses unknown, cancelled and other products’ codes', function () {
    callServer('activate', ['code' => 'AAAA-BBBB-CCCC-DDDD', 'domain' => 'shop.example.com'])->assertStatus(422)->assertJsonPath('message', 'This purchase code is not valid. Check it and try again.');
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com', 'product' => 'other-app'])->assertStatus(422);

    $this->license->update(['revoked_at' => now()]);
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertStatus(422)->assertJsonPath('message', 'This purchase code has been cancelled. Please contact the seller.');

    expect(Activation::query()->count())->toBe(0);
});

it('confirms an activation with a fresh certificate', function () {
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com']);
    $this->travel(8)->days();

    $certificate = certificateFrom(callServer('check', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertOk());

    expect($certificate->get('status'))->toBe('active')
        ->and($certificate->checkedAt()->isSameMinute(now()))->toBeTrue()
        ->and(Activation::query()->sole()->last_checked_at->isSameMinute(now()))->toBeTrue();
});

it('answers a check with a signed "no" once cancelled, released or deleted', function () {
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com']);
    $check = fn () => certificateFrom(callServer('check', ['code' => $this->license->code, 'domain' => 'shop.example.com']));

    $this->license->update(['revoked_at' => now(), 'revoked_reason' => 'refund']);
    expect($check()->get('status'))->toBe('revoked')->and($check()->get('message'))->toBe('This license was cancelled by the seller: refund.');

    $this->license->update(['revoked_at' => null, 'revoked_reason' => null]);
    expect($check()->get('status'))->toBe('active');

    Activation::query()->update(['deactivated_at' => now()]);
    expect($check()->get('status'))->toBe('revoked')->and($check()->get('message'))->toContain('no longer activated for shop.example.com');

    $this->license->delete();
    expect($check()->get('message'))->toBe('This purchase code no longer exists. Please contact the seller.');
});

it('frees a domain when a site releases it', function () {
    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com']);
    callServer('deactivate', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertOk();

    callServer('activate', ['code' => $this->license->code, 'domain' => 'new.example.com'])->assertOk();
    expect($this->license->activeActivations()->pluck('domain')->all())->toBe(['new.example.com']);
});

it('is closed where the license server is not switched on', function () {
    config(['licensing.server.enabled' => false]);

    callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com'])->assertNotFound();
    $this->actingAs(userWithRole(UserRole::Admin))->get('/admin/licenses')->assertNotFound();
});

it('never checks its own license', function () {
    expect(app(ClientLicense::class)->isLicenseServer())->toBeTrue()
        ->and(app(ClientLicense::class)->configured())->toBeFalse();

    menuItem();
    $this->get('http://lesen.seller.com/')->assertOk();
});

describe('the admin', function () {
    beforeEach(fn () => $this->actingAs(userWithRole(UserRole::Admin)));

    it('lists codes with their sites', function () {
        callServer('activate', ['code' => $this->license->code, 'domain' => 'shop.example.com']);

        $this->get('/admin/licenses')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Licenses/Index')
            ->where('stats', ['licenses' => 1, 'active_sites' => 1, 'revoked' => 0])
            ->where('licenses.0.code', $this->license->code)
            ->where('licenses.0.activations.0.domain', 'shop.example.com')
            ->where('licenses.0.activations.0.active', true)
            ->where('auth.user.licensing', ['client' => false, 'server' => true, 'update' => null]));

        $this->get('/admin/licenses?search=shop.example')->assertInertia(fn (Assert $page) => $page->has('licenses', 1));
        $this->get('/admin/licenses?search=nothing-like-this')->assertInertia(fn (Assert $page) => $page->has('licenses', 0));
    });

    it('creates, edits, cancels and restores codes', function () {
        $this->post('/admin/licenses', ['customer_name' => 'Restoran Siti', 'customer_email' => 'siti@example.com', 'max_activations' => 2, 'notes' => 'Paid RM 1,500'])
            ->assertSessionHas('success', fn ($message) => str_starts_with($message, 'Purchase code for Restoran Siti: '));

        $siti = License::query()->where('customer_name', 'Restoran Siti')->sole();
        expect($siti->max_activations)->toBe(2)->and($siti->product)->toBe('order-system');

        $this->put("/admin/licenses/{$siti->id}", ['customer_name' => 'Restoran Siti Sdn Bhd', 'max_activations' => 3])->assertSessionHas('success');
        expect($siti->refresh()->customer_name)->toBe('Restoran Siti Sdn Bhd')->and($siti->max_activations)->toBe(3);

        $this->post("/admin/licenses/{$siti->id}/revoke", ['reason' => 'Chargeback'])->assertSessionHas('success');
        expect($siti->refresh()->isRevoked())->toBeTrue()->and($siti->revoked_reason)->toBe('Chargeback');

        $this->post("/admin/licenses/{$siti->id}/restore")->assertSessionHas('success');
        expect($siti->refresh()->isRevoked())->toBeFalse();

        $this->post('/admin/licenses', ['customer_name' => '', 'max_activations' => 0])->assertSessionHasErrors(['customer_name', 'max_activations']);
    });

    it('frees a domain for a customer who moved', function () {
        callServer('activate', ['code' => $this->license->code, 'domain' => 'old.example.com']);
        $activation = Activation::query()->sole();

        $this->delete("/admin/licenses/{$this->license->id}/activations/{$activation->id}")->assertSessionHas('success');

        callServer('activate', ['code' => $this->license->code, 'domain' => 'new.example.com'])->assertOk();

        $other = License::query()->create(['code' => License::newCode(), 'product' => 'order-system', 'customer_name' => 'Other']);
        $this->delete("/admin/licenses/{$other->id}/activations/{$activation->id}")->assertNotFound();
    });

    it('is for super admins only', function () {
        $this->actingAs(userWithRole(UserRole::Manager))->get('/admin/licenses')->assertForbidden();
    });
});

it('creates codes from the command line', function () {
    $this->artisan('license:create', ['customer' => 'Kedai CLI', '--email' => 'cli@example.com', '--max' => 2])->assertSuccessful();

    expect(License::query()->where('customer_name', 'Kedai CLI')->sole()->max_activations)->toBe(2);
});

it('writes the public key and server address, and will not replace a key by accident', function () {
    $path = config_path('licensing.php');
    $original = file_get_contents($path);

    try {
        config(['licensing.public_key' => '']);
        $this->artisan('license:keygen', ['--server' => 'https://lesen.seller.com'])
            ->expectsOutputToContain('LICENSE_SIGNING_KEY=')
            ->assertSuccessful();

        $written = include $path;
        expect($written['public_key'])->toStartWith('-----BEGIN PUBLIC KEY-----')
            ->and(file_get_contents($path))->toContain("env('LICENSE_SERVER_URL', 'https://lesen.seller.com')");

        config(['licensing.public_key' => $written['public_key']]);
        $this->artisan('license:keygen', ['--server' => 'https://lesen.seller.com'])->assertFailed();
        $this->artisan('license:keygen', ['--server' => 'http://insecure.example.com', '--force' => true])->assertFailed();
    } finally {
        file_put_contents($path, $original);
    }
});
