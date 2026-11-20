<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lets the crew "86" an item the moment it runs out, without waiting for an
 * admin. Only Available ⇄ Sold Out can be changed from the kitchen.
 */
class KitchenStockController extends Controller
{
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ProductStatus::Available->value, ProductStatus::SoldOut->value])],
        ]);

        abort_unless(
            in_array($product->status, [ProductStatus::Available, ProductStatus::SoldOut], true),
            422,
            'This item is managed by an admin.',
        );

        $product->update(['status' => $validated['status']]);

        return back()->with('success', $product->status === ProductStatus::SoldOut
            ? "{$product->name} is marked sold out."
            : "{$product->name} is back on the menu.");
    }
}
