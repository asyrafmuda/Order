<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\User;
use App\Rules\UniquePhone;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Account/Profile', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get(),
            'zones' => DeliveryZone::query()->active()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Staff sign in with their email, so it stays required for them.
            'email' => [$user->isStaff() ? 'required' : 'nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{9,16}$/', new UniquePhone($user->id)],
        ]);

        $user->update([...$validated, 'phone' => Phone::normalize($validated['phone'])]);

        return back()->with('success', 'Profile updated.');
    }
}
