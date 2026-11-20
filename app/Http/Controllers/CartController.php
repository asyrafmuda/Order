<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidCartLine;
use App\Http\Requests\AddToCartRequest;
use App\Services\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart) {}

    public function store(AddToCartRequest $request): RedirectResponse
    {
        try {
            $this->cart->add($request->validated());
        } catch (InvalidCartLine $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // No flash message: the ticket and its total update on screen, and a
        // toast per tap would only cover the menu on a phone.
        return back();
    }

    public function update(Request $request, string $line): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        $this->cart->setQuantity($line, $validated['quantity']);

        return back();
    }

    public function combo(Request $request, string $line): RedirectResponse
    {
        $validated = $request->validate(['combo_choice' => ['nullable', 'string', 'max:100']]);

        $this->cart->upgradeToCombo($line, $validated['combo_choice'] ?? null);

        return back()->with('success', 'Upgraded to a combo set.');
    }

    public function destroy(string $line): RedirectResponse
    {
        $this->cart->remove($line);

        return back();
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return back();
    }
}
