<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Order;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => Phone::normalize($request->validated('phone')),
            'password' => $request->validated('pin'),
            'role' => UserRole::Customer,
        ]);

        // Orders placed as a guest in this browser session move into the new account.
        Order::query()
            ->whereNull('user_id')
            ->whereIn('uuid', $request->session()->get('guest_orders', []))
            ->update(['user_id' => $user->id]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('menu.index'))->with('success', "Welcome to the crew, {$user->name}!");
    }
}
