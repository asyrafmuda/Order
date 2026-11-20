<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\Cart\Cart;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * Dine-in by QR code. Each table's code points at /t/{token}; scanning it
 * seats the customer's session at that table and opens the menu.
 */
class TableController extends Controller
{
    public function show(string $token, Cart $cart, Settings $settings, OutletContext $outlets): RedirectResponse
    {
        $table = DiningTable::query()->active()->with('outlet')->where('token', $token)->first();

        // The code decides the outlet: a customer sitting in one branch is
        // ordering from that branch, whatever they browsed before.
        if ($table?->outlet->is_active) {
            $outlets->switchTo($table->outlet);
        }

        if (! $table || ! $table->outlet->is_active || ! $settings->bool('ordering.dine_in_enabled')) {
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
