<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderLookupController extends Controller
{
    public function index(Request $request): Response
    {
        $recent = Order::query()
            ->with('items')
            ->where(function ($query) use ($request) {
                $query->whereIn('uuid', $request->session()->get('guest_orders', []));

                if ($request->user()) {
                    $query->orWhere('user_id', $request->user()->id);
                }
            })
            ->where('created_at', '>=', now()->subDays(2))
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('Orders/Track', [
            'recent' => OrderResource::collection($recent)->resolve($request),
        ]);
    }

    /**
     * Find a ticket by queue number + the phone number it was ordered with.
     *
     * @throws ValidationException
     */
    public function find(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:12'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $order = Order::query()
            ->where('order_number', strtoupper(ltrim(trim($validated['order_number']), '#')))
            ->where('customer_phone', Phone::normalize($validated['phone']))
            ->where('created_at', '>=', now()->subDays(2))
            ->latest()
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_number' => 'We could not find a recent order with that number and phone.',
            ]);
        }

        $request->session()->push('guest_orders', $order->uuid);

        return redirect()->route('orders.show', $order);
    }
}
