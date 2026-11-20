<?php

namespace App\Http\Controllers\Counter;

use App\Actions\Delivery\AssignRider;
use App\Actions\Delivery\MarkPickedUp;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CompleteOrder;
use App\Actions\Payments\CollectCashPayment;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignRiderRequest;
use App\Http\Requests\Staff\CancelOrderRequest;
use App\Http\Requests\Staff\VerifyPickupRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CounterOrderController extends Controller
{
    /**
     * Look an open order up by its pickup code (any day) or today's queue
     * number, then open its hand-over panel.
     *
     * @throws ValidationException
     */
    public function verify(VerifyPickupRequest $request): RedirectResponse
    {
        $code = $request->code();

        $order = Order::query()->forOutlet($this->outlet())
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->where(fn ($query) => $query
                ->where('pickup_code', $code)
                ->orWhere(fn ($today) => $today
                    ->where('order_number', $code)
                    ->where('business_date', now()->toDateString())))
            ->latest('id')
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'code' => "No open order matches \"{$code}\". Check the pickup code or today's order number.",
            ]);
        }

        return redirect()->route('counter.index', ['order' => $order->uuid]);
    }

    public function pay(Request $request, Order $order, CollectCashPayment $collect): RedirectResponse
    {
        $order = $collect->handle($order, $request->user());

        return back()->with('success', "Cash collected for order {$order->order_number}.");
    }

    public function complete(Request $request, Order $order, CompleteOrder $complete): RedirectResponse
    {
        $order = $complete->handle($order, $request->user());

        return back()->with('success', "Order {$order->order_number} handed over.");
    }

    public function rider(AssignRiderRequest $request, Order $order, AssignRider $assign): RedirectResponse
    {
        $rider = $request->rider();

        $assign->handle($order, $rider);

        return back()->with('success', $rider
            ? "{$rider->user->name} will deliver order {$order->order_number}."
            : "Order {$order->order_number} is waiting for a rider again.");
    }

    /**
     * The rider collected the order at the counter.
     *
     * @throws OrderException
     */
    public function dispatch(Request $request, Order $order, MarkPickedUp $pickUp): RedirectResponse
    {
        $delivery = $order->delivery;

        if (! $delivery) {
            throw new OrderException("Order {$order->order_number} is not a delivery order.");
        }

        $pickUp->handle($delivery, $request->user());

        return back()->with('success', "Order {$order->order_number} is out for delivery.");
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $order = $cancel->handle($order, $request->validated('reason'), $request->user());

        return back()->with('success', "Order {$order->order_number} was cancelled.");
    }
}
