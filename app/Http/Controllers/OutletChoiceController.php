<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\Cart\Cart;
use App\Support\OutletContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer chooses which outlet to order from. The ticket is kept; items
 * that outlet has run out of drop off it the next time it is priced.
 */
class OutletChoiceController extends Controller
{
    public function __invoke(Request $request, OutletContext $outlets, Cart $cart): RedirectResponse
    {
        abort_unless($outlets->multi(), 404);

        $validated = $request->validate([
            'outlet_id' => ['required', Rule::exists(Outlet::class, 'id')->where('is_active', true)],
        ]);

        $outlet = Outlet::query()->findOrFail($validated['outlet_id']);

        // A table and a delivery area belong to the outlet being left.
        $cart->leaveTable();
        $cart->setZone(null);
        $outlets->switchTo($outlet);

        return back()->with('success', "You are now ordering from {$outlet->name}.");
    }
}
