<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

function customerWithPin(string $phone = '60123456789', string $pin = '135790'): User
{
    return User::factory()->create(['phone' => $phone, 'email' => null, 'password' => $pin, 'role' => UserRole::Customer]);
}

it('signs a customer in with just a mobile number and PIN', function (string $typed) {
    $customer = customerWithPin();

    $this->post(route('login.store'), ['login' => $typed, 'password' => '135790'])
        ->assertRedirect(route('menu.index'));

    $this->assertAuthenticatedAs($customer);
})->with(['012-345 6789', '0123456789', '123456789', '+60 12 345 6789', '60123456789']);

it('still signs in with an email address', function () {
    $user = userWithRole(UserRole::Customer);

    $this->post(route('login.store'), ['login' => strtoupper($user->email), 'password' => 'password'])
        ->assertRedirect(route('menu.index'));

    $this->assertAuthenticatedAs($user);
});

it('sends staff to their own workspace after signing in', function (UserRole $role, string $routeName) {
    $user = userWithRole($role);

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect(route($routeName));
})->with([
    [UserRole::Admin, 'admin.dashboard'],
    [UserRole::Cashier, 'counter.index'],
    [UserRole::Waiter, 'pos.index'],
    [UserRole::Kitchen, 'kitchen.index'],
    [UserRole::Rider, 'rider.index'],
]);

it('rejects a wrong PIN and deactivated accounts', function () {
    $customer = customerWithPin();

    $this->post(route('login.store'), ['login' => '0123456789', 'password' => '000001'])->assertSessionHasErrors('login');

    $customer->update(['is_active' => false]);
    $this->post(route('login.store'), ['login' => '0123456789', 'password' => '135790'])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('locks out after repeated failures from one device', function () {
    customerWithPin();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['login' => '0123456789', 'password' => '000001']);
    }

    $this->post(route('login.store'), ['login' => '0123456789', 'password' => '135790'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('locks an account against PIN guessing spread across many addresses', function () {
    customerWithPin();

    foreach (range(1, 10) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->post(route('login.store'), ['login' => '0123456789', 'password' => '000001']);
    }

    // A fresh address, and even the right PIN: still locked.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.9.9'])
        ->post(route('login.store'), ['login' => '0123456789', 'password' => '135790'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('registers with a name, mobile number and PIN, and adopts guest orders', function () {
    $order = placeOrder();

    $this->withSession(['guest_orders' => [$order->uuid]])
        ->post(route('register.store'), ['name' => 'Nurul Huda', 'phone' => '019-888 7766', 'pin' => '246813'])
        ->assertRedirect(route('menu.index'));

    $user = User::query()->where('phone', '60198887766')->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->email)->toBeNull()
        ->and($order->refresh()->user_id)->toBe($user->id);

    $this->assertAuthenticatedAs($user);
});

it('refuses weak PINs and anything that is not six digits', function (string $pin) {
    $this->post(route('register.store'), ['name' => 'A', 'phone' => '0198887766', 'pin' => $pin])
        ->assertSessionHasErrors('pin');

    expect(User::query()->count())->toBe(0);
})->with(['123456', '111111', '12345', '1234567', 'abcdef']);

it('allows one account per mobile number, however it is typed', function () {
    customerWithPin('60198887766');

    $this->post(route('register.store'), ['name' => 'Other', 'phone' => '+60 19-888 7766', 'pin' => '246813'])
        ->assertSessionHasErrors('phone');
});

it('saves a mobile number the same way however it is typed', function (string $typed) {
    $this->post(route('register.store'), ['name' => 'Aina', 'phone' => $typed, 'pin' => '246813'])->assertSessionHasNoErrors();

    expect(User::query()->sole()->phone)->toBe('60123456789');
})->with(['0123456789', '012-345 6789', '60123456789', '+60 12-345 6789', '123456789']);

it('only accepts Malaysian mobile numbers when registering', function (string $typed) {
    $this->post(route('register.store'), ['name' => 'Aina', 'phone' => $typed, 'pin' => '246813'])
        ->assertSessionHasErrors(['phone' => 'Enter a Malaysian mobile number, e.g. 012-345 6789.']);
})->with(['03-1234 5678', '0123', 'abc0123456789', '0123456789012']);

it('never lets registration choose a role', function () {
    $this->post(route('register.store'), ['name' => 'Sneaky', 'phone' => '0123456789', 'pin' => '246813', 'role' => 'admin']);

    expect(User::query()->where('phone', '60123456789')->sole()->role)->toBe(UserRole::Customer);
});

it('emails a reset link when the account has an email, and accepts the new password', function () {
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

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'new-burger-sauce-42'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('lets a customer change their PIN, and staff reset one at the counter', function () {
    $customer = customerWithPin();

    $this->actingAs($customer)
        ->put(route('account.password.update'), ['current_password' => '135790', 'password' => '864209', 'password_confirmation' => '864209'])
        ->assertSessionHas('success');

    // Forgot it, no email on the account: an admin sets a new one.
    $this->actingAs(userWithRole(UserRole::Admin))
        ->put(route('admin.users.update', $customer), [
            'name' => $customer->name, 'email' => null, 'phone' => '0123456789', 'role' => 'customer', 'is_active' => true, 'password' => '975310',
        ])->assertSessionHas('success');

    auth()->logout();
    $this->post(route('login.store'), ['login' => '0123456789', 'password' => '975310'])->assertRedirect(route('menu.index'));
});

it('keeps full passwords for staff', function () {
    $this->actingAs(userWithRole(UserRole::Cashier))
        ->put(route('account.password.update'), ['current_password' => 'password', 'password' => '864209', 'password_confirmation' => '864209'])
        ->assertSessionHasErrors('password');
});

it('signs out', function () {
    $this->actingAs(userWithRole(UserRole::Customer))->post(route('logout'))->assertRedirect(route('menu.index'));

    $this->assertGuest();
});
