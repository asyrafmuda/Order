<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\Cart\Cart;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * Ordering at the counter by QR code. Each outlet has one code, shown at the
 * counter or on the kiosk; it points at /s/{token}. Scanning it opens that
 * outlet's menu with the customer marked as standing there, so they can order
 * without giving a name and pay on the spot.
 */
class ShopCodeController extends Controller
{
    public function show(string $token, Cart $cart, Settings $settings, OutletContext $outlets): RedirectResponse
    {
        $outlet = Outlet::query()->active()->where('qr_token', $token)->first();

        // A business running as one outlet has one counter: the main outlet's.
        if (! $outlet || (! $outlets->multi() && ! $outlet->is_default)) {
            return redirect()->route('menu.index')->with('error', 'That code is no longer active. Please ask our crew.');
        }

        // The code decides the outlet, whatever the customer browsed before.
        $outlets->switchTo($outlet);

        if (! $settings->bool('ordering.pickup_enabled')) {
            return redirect()->route('menu.index')->with('error', 'Ordering by QR code is not available here right now. Please order with our crew.');
        }

        $cart->arriveAtCounter($outlet);

        return redirect()->route('menu.index')->with('success', 'Order here and collect at the counter when your number is called.');
    }

    public function destroy(Cart $cart): RedirectResponse
    {
        $cart->leaveCounter();

        return back();
    }
}
