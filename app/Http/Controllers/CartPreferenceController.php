<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Models\DeliveryZone;
use App\Services\Cart\Cart;
use App\Support\OutletContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pickup vs delivery and the delivery area, chosen before checkout so the
 * ticket shows the right fee.
 */
class CartPreferenceController extends Controller
{
    public function __invoke(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::enum(OrderType::class)->only(OrderType::online())],
            'zone_id' => ['sometimes', 'nullable', Rule::exists(DeliveryZone::class, 'id')->where('is_active', true)->where('outlet_id', app(OutletContext::class)->current()->id)],
        ]);

        if (isset($validated['type'])) {
            $cart->setType(OrderType::from($validated['type']));
        }

        if (array_key_exists('zone_id', $validated)) {
            $cart->setZone($validated['zone_id'] ? (int) $validated['zone_id'] : null);
        }

        return back();
    }
}
