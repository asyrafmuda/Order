<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('signs customers in and sends them back to the menu', function () {
    $user = userWithRole(UserRole::Customer);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('menu.index'));

    $this->assertAuthenticatedAs($user);
});

it('sends staff to their own workspace after signing in', function (UserRole $role, string $routeName) {
    $user = userWithRole($role);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route($routeName));
})->with([
    [UserRole::Admin, 'admin.dashboard'],
    [UserRole::Cashier, 'counter.index'],
    [UserRole::Kitchen, 'kitchen.index'],
    [UserRole::Rider, 'rider.index'],
]);

it('rejects wrong passwords and deactivated accounts', function () {
    $user = userWithRole(UserRole::Cashier);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');

    $user->update(['is_active' => false]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks out after repeated failures', function () {
    $user = userWithRole(UserRole::Customer);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'nope']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('registers a customer and adopts their guest orders', function () {
    $order = placeOrder();

    $this->withSession(['guest_orders' => [$order->uuid]])
        ->post(route('register.store'), [
            'name' => 'Nurul Huda',
            'email' => 'nurul@example.com',
            'phone' => '019-888 7766',
            'password' => 'burger-sauce-42',
            'password_confirmation' => 'burger-sauce-42',
        ])
        ->assertRedirect(route('menu.index'));

    $user = User::query()->where('email', 'nurul@example.com')->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->phone)->toBe('60198887766')
        ->and($order->refresh()->user_id)->toBe($user->id);
});

it('never lets registration choose a role', function () {
    $this->post(route('register.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'phone' => '0123456789',
        'role' => 'admin',
        'password' => 'burger-sauce-42',
        'password_confirmation' => 'burger-sauce-42',
    ]);

    expect(User::query()->where('email', 'sneaky@example.com')->sole()->role)->toBe(UserRole::Customer);
});

it('emails a reset link and accepts the new password', function () {
    Notification::fake();
    $user = userWithRole(UserRole::Customer);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');
    $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-burger-sauce-42',
            'password_confirmation' => 'new-burger-sauce-42',
        ])->assertRedirect(route('login'));

        return true;
    });

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-burger-sauce-42'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('signs out', function () {
    $this->actingAs(userWithRole(UserRole::Customer))->post(route('logout'))->assertRedirect(route('menu.index'));

    $this->assertGuest();
});
