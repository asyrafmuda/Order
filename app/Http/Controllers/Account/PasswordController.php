<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Rules\PinOrPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            // Customers may use a 6-digit PIN; staff need a full password.
            'password' => ['required', 'confirmed', $request->user()->isStaff() ? Password::defaults() : new PinOrPassword],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return back()->with('success', 'Your PIN / password was changed.');
    }
}
