<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The outlet's shop QR code: one code for the counter or kiosk, which
 * customers scan to order on the spot. A kiosk with no tables needs only
 * this one.
 */
class ShopQrController extends Controller
{
    /**
     * A print-ready poster for the counter.
     */
    public function print(Settings $settings): Response
    {
        return Inertia::render('Admin/Tables/ShopPoster', [
            'shopCode' => self::present($this->outlet(), $settings),
        ]);
    }

    /**
     * Issue a new code, e.g. when the printed one was shared around. The old
     * code stops working immediately.
     */
    public function regenerate(): RedirectResponse
    {
        $this->outlet()->update(['qr_token' => Outlet::newQrToken()]);

        return back()->with('success', 'New shop QR code issued. Reprint the poster — the old code no longer works.');
    }

    /**
     * @return array{url: string, outlet: string, usable: bool}
     */
    public static function present(Outlet $outlet, Settings $settings): array
    {
        return [
            'url' => route('shop.show', $outlet->qr_token),
            'outlet' => $outlet->name,
            // Scanning only works while the outlet is open to customers and takes pickup orders.
            'usable' => $outlet->is_active && (bool) $settings->forOutlet($outlet)['ordering.pickup_enabled'],
        ];
    }
}
