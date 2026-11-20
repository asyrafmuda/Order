<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\Cart\Cart;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * Dine-in by QR code. Each table's code points at /t/{token}; scanning it
 * seats the customer's session at that table and opens the menu.
 */
class TableController extends Controller
{
    public function show(string $token, Cart $cart, Settings $settings): RedirectResponse
    {
        $table = DiningTable::query()->active()->where('token', $token)->first();

        if (! $table || ! $settings->bool('ordering.dine_in_enabled')) {
            return redirect()->route('menu.index')->with('error', 'That table code is not active. Please ask our crew, or order for pickup instead.');
        }

        $cart->sitAt($table);

        return redirect()->route('menu.index')->with('success', "You are ordering for {$table->name}. We will bring your food to you.");
    }

    public function destroy(Cart $cart): RedirectResponse
    {
        $cart->leaveTable();

        return back();
    }
}
