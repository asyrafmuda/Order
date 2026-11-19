<?php

namespace App\Http\Controllers\Rider;

use App\Actions\Delivery\MarkDelivered;
use App\Actions\Delivery\MarkPickedUp;
use App\Enums\DeliveryStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RiderDeliveryController extends Controller
{
    /**
     * The rider collected the order from the counter.
     */
    public function pickup(Request $request, Delivery $delivery, MarkPickedUp $pickUp): RedirectResponse
    {
        Gate::authorize('update', $delivery);

        $delivery = $pickUp->handle($delivery, $request->user());

        return back()->with('success', "Order {$delivery->order->order_number} picked up. Ride safe!");
    }

    /**
     * @throws OrderException
     */
    public function deliver(Request $request, Delivery $delivery, MarkDelivered $deliver): RedirectResponse
    {
        Gate::authorize('update', $delivery);

        if ($delivery->status !== DeliveryStatus::PickedUp) {
            throw new OrderException('Mark the order as picked up before delivering it.');
        }

        $delivery = $deliver->handle($delivery, $request->user());

        return back()->with('success', "Order {$delivery->order->order_number} delivered. Nice work!");
    }
}
