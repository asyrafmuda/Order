<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\OutletStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lets the crew "86" an item the moment their outlet runs out, without
 * waiting for an admin. It only affects this outlet: other branches keep
 * selling the item.
 */
class KitchenStockController extends Controller
{
    public function update(Request $request, Product $product, OutletStock $stock): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ProductStatus::Available->value, ProductStatus::SoldOut->value])],
        ]);

        // Hidden, coming soon or switched off everywhere: only an admin changes those.
        abort_unless($product->status === ProductStatus::Available, 422, 'This item is managed by an admin.');

        $soldOut = $validated['status'] === ProductStatus::SoldOut->value;

        $stock->mark($product, $soldOut, $this->outlet());

        return back()->with('success', $soldOut
            ? "{$product->name} is marked sold out."
            : "{$product->name} is back on the menu.");
    }
}
