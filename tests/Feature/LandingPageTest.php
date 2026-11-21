<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('is hidden unless switched on, so customers\' shops never show it', function () {
    config(['landing.enabled' => false]);

    $this->get('/web')->assertNotFound();
});

it('sells the system with the price, demo links and share preview', function () {
    config(['landing.enabled' => true, 'landing.product' => 'OrderLa', 'landing.price' => 'RM 2,500', 'landing.whatsapp' => '+60 12-345 6789']);

    $this->get('/web')->assertOk()
        ->assertSee('Pelanggan scan.', false)
        ->assertSee('RM 2,500')
        ->assertSee('https://wa.me/60123456789?text=', false)
        ->assertSee('og:image', false)
        ->assertSee(url('/pos'), false)
        ->assertDontSee('Hubungi kami untuk harga');
});

it('asks to get in touch when no price is set, and hides contact buttons that are not set', function () {
    config(['landing.enabled' => true, 'landing.price' => '', 'landing.whatsapp' => null, 'landing.email' => null]);

    $this->get('/web')->assertOk()->assertSee('Hubungi kami')->assertDontSee('wa.me', false);
});

it('only lists demo logins that really work', function () {
    config(['landing.enabled' => true]);
    User::factory()->role(UserRole::Admin)->create(['email' => 'admin@bossburger.test', 'password' => Hash::make('password')]);
    User::factory()->role(UserRole::Cashier)->create(['email' => 'cashier@bossburger.test', 'password' => Hash::make('changed-already')]);

    $response = $this->get('/web')->assertOk();

    $response->assertSee('admin@bossburger.test')->assertDontSee('cashier@bossburger.test');
    expect(User::query()->count())->toBe(2);
});
