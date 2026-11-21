<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function store(AddressRequest $request): RedirectResponse
    {
        $address = $request->user()->addresses()->create($this->attributes($request));

        $this->syncDefault($request->user(), $address);

        return back()->with('success', 'Address saved.');
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        $address->update($this->attributes($request));

        $this->syncDefault($request->user(), $address);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    /**
     * Only one address per customer is the default.
     */
    private function syncDefault(User $user, Address $address): void
    {
        if ($address->is_default) {
            $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AddressRequest $request): array
    {
        return [...$request->validated(), 'phone' => Phone::normalize($request->validated('phone'))];
    }
}
