<?php

use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = userWithRole(UserRole::Admin);
    $this->actingAs($this->admin);
});

describe('users', function () {
    it('creates a staff user', function () {
        $this->post(route('admin.users.store'), [
            'name' => 'New Cashier', 'email' => 'cash@example.com', 'phone' => '012-888 7777',
            'role' => 'cashier', 'is_active' => true, 'password' => 'secret-pass-1',
        ])->assertSessionHas('success');

        $user = User::query()->where('email', 'cash@example.com')->sole();

        expect($user->role)->toBe(UserRole::Cashier)
            ->and($user->phone)->toBe('60128887777')
            ->and(Hash::check('secret-pass-1', $user->password))->toBeTrue()
            ->and($user->rider)->toBeNull();
    });

    it('creates the rider profile for rider-role users', function () {
        $this->post(route('admin.users.store'), [
            'name' => 'Rider Zul', 'email' => 'zul@example.com', 'phone' => null,
            'role' => 'rider', 'is_active' => true, 'password' => 'secret-pass-1',
        ])->assertSessionHas('success');

        expect(User::query()->where('email', 'zul@example.com')->sole()->rider)->not->toBeNull();

        // Promoting an existing user to rider creates the profile too.
        $customer = userWithRole(UserRole::Customer);
        $this->put(route('admin.users.update', $customer), [
            'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone,
            'role' => 'rider', 'is_active' => true, 'password' => '',
        ])->assertSessionHas('success');

        expect($customer->refresh()->rider)->not->toBeNull();
    });

    it('requires a password only when creating', function () {
        $this->post(route('admin.users.store'), [
            'name' => 'No Pass', 'email' => 'nopass@example.com', 'role' => 'kitchen', 'is_active' => true, 'password' => '',
        ])->assertSessionHasErrors('password');

        $user = userWithRole(UserRole::Kitchen);
        $old = $user->password;

        $this->put(route('admin.users.update', $user), [
            'name' => 'Renamed', 'email' => $user->email, 'phone' => null, 'role' => 'kitchen', 'is_active' => true, 'password' => '',
        ])->assertSessionHas('success');

        expect($user->refresh()->name)->toBe('Renamed')->and($user->password)->toBe($old);
    });

    it('stops an admin from demoting, deactivating or deleting themselves', function () {
        $payload = ['name' => $this->admin->name, 'email' => $this->admin->email, 'phone' => null, 'password' => ''];

        $this->put(route('admin.users.update', $this->admin), [...$payload, 'role' => 'customer', 'is_active' => true])
            ->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', $this->admin), [...$payload, 'role' => 'admin', 'is_active' => false])
            ->assertSessionHasErrors('is_active');
        $this->delete(route('admin.users.destroy', $this->admin))->assertSessionHas('error');

        $this->admin->refresh();

        expect($this->admin->role)->toBe(UserRole::Admin)
            ->and($this->admin->is_active)->toBeTrue();

        // Editing their own name is still fine.
        $this->put(route('admin.users.update', $this->admin), [...$payload, 'name' => 'Big Boss', 'role' => 'admin', 'is_active' => true])
            ->assertSessionHas('success');
        expect($this->admin->refresh()->name)->toBe('Big Boss');
    });

    it('deletes unused accounts but deactivates ones with orders', function () {
        $unused = userWithRole(UserRole::Kitchen);
        $customer = userWithRole(UserRole::Customer);
        placeOrder(['user' => $customer]);

        $this->delete(route('admin.users.destroy', $unused))->assertSessionHas('success');
        $this->delete(route('admin.users.destroy', $customer))->assertSessionHas('success');

        expect(User::query()->whereKey($unused->id)->exists())->toBeFalse()
            ->and($customer->refresh()->is_active)->toBeFalse();
    });

    it('rejects duplicate emails', function () {
        $existing = userWithRole(UserRole::Customer);

        $this->post(route('admin.users.store'), [
            'name' => 'Dup', 'email' => $existing->email, 'role' => 'cashier', 'is_active' => true, 'password' => 'secret-pass-1',
        ])->assertSessionHasErrors('email');
    });

    it('filters users by role and search', function () {
        $kitchen = userWithRole(UserRole::Kitchen);
        userWithRole(UserRole::Customer);

        $this->get(route('admin.users.index', ['role' => 'kitchen']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $kitchen->id));

        $this->get(route('admin.users.index', ['search' => $kitchen->email]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
    });
});

describe('riders', function () {
    it('creates a rider with a login and profile', function () {
        $this->post(route('admin.riders.store'), [
            'name' => 'Rider Hakim', 'email' => 'hakim@example.com', 'phone' => '019-222 3333', 'password' => 'secret-pass-1',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'VAB 123', 'is_available' => true, 'is_active' => true,
        ])->assertSessionHas('success');

        $rider = Rider::query()->with('user')->where('plate_number', 'VAB 123')->sole();

        expect($rider->user->role)->toBe(UserRole::Rider)
            ->and($rider->user->phone)->toBe('60192223333')
            ->and($rider->is_available)->toBeTrue();
    });

    it('updates a rider and their account', function () {
        $rider = userWithRole(UserRole::Rider)->rider;

        $this->put(route('admin.riders.update', $rider), [
            'name' => 'Updated Rider', 'email' => $rider->user->email, 'phone' => '0123456789', 'password' => '',
            'vehicle_type' => 'Car', 'plate_number' => 'NEW 1', 'is_available' => false, 'is_active' => true,
        ])->assertSessionHas('success');

        $rider->refresh()->load('user');

        expect($rider->user->name)->toBe('Updated Rider')
            ->and($rider->vehicle_type)->toBe('Car')
            ->and($rider->is_available)->toBeFalse();
    });

    it('deletes a rider who never delivered', function () {
        $user = userWithRole(UserRole::Rider);

        $this->delete(route('admin.riders.destroy', $user->rider))->assertSessionHas('success');

        expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
            ->and(Rider::query()->count())->toBe(0);
    });

    it('deactivates a rider with delivery history instead of deleting', function () {
        $user = userWithRole(UserRole::Rider);
        $order = placeOrder(['type' => OrderType::Delivery]);
        $order->delivery->update(['rider_id' => $user->rider->id]);

        $this->delete(route('admin.riders.destroy', $user->rider))->assertSessionHas('success');

        expect($user->refresh()->is_active)->toBeFalse()
            ->and($user->rider->is_available)->toBeFalse()
            ->and($order->delivery->refresh()->rider_id)->toBe($user->rider->id);
    });

    it('lists riders with their delivery counts', function () {
        $user = userWithRole(UserRole::Rider);
        $order = placeOrder(['type' => OrderType::Delivery]);
        $order->delivery->update(['rider_id' => $user->rider->id, 'status' => 'assigned']);

        $this->get(route('admin.riders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('riders', 1)
                ->where('riders.0.deliveries_count', 1)
                ->where('riders.0.active_deliveries_count', 1));
    });
});
