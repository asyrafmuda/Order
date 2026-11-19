<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\StartPayment;
use App\Data\CheckoutData;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Requests\CheckoutRequest;
use App\Models\DeliveryZone;
use App\Services\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function create(Request $request, Cart $cart): Response|RedirectResponse
    {
        if ($cart->priced()->isEmpty()) {
            return redirect()->route('menu.index')->with('error', 'Your ticket is empty. Add something tasty first.');
        }

        $user = $request->user();

        return Inertia::render('Checkout/Create', [
            'zones' => DeliveryZone::query()->active()->orderBy('sort_order')->get(['id', 'name', 'areas', 'fee', 'min_order', 'eta_minutes']),
            'paymentMethods' => array_map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'pickup_only' => ! $method->isOnline(),
            ], PaymentMethod::cases()),
            'customer' => [
                'name' => $user?->name ?? '',
                'phone' => $user?->phone ?? '',
                'email' => $user?->email ?? '',
            ],
            'addresses' => $user
                ? $user->addresses()->orderByDesc('is_default')->latest()->get()
                : [],
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder, StartPayment $startPayment): SymfonyResponse
    {
        $data = CheckoutData::fromArray($request->validated());
        $order = $placeOrder->handle($cart->priced(), $data, $request->user());

        $cart->clear();

        // Lets a guest find this ticket again from the tracking page.
        $request->session()->push('guest_orders', $order->uuid);

        if ($request->user() && $request->boolean('save_address') && $order->type === OrderType::Delivery) {
            $request->user()->addresses()->firstOrCreate(
                ['line1' => $data->line1, 'postcode' => $data->postcode],
                [
                    'delivery_zone_id' => $order->delivery?->delivery_zone_id,
                    'recipient_name' => $data->recipientName ?: $data->customerName,
                    'phone' => $data->recipientPhone ?: $data->customerPhone,
                    'line2' => $data->line2,
                    'city' => $data->city,
                    'notes' => $data->deliveryNotes,
                ],
            );
        }

        if ($data->paymentMethod->isOnline()) {
            return Inertia::location($startPayment->handle($order));
        }

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} is in! Pay at the counter when you collect.");
    }
}
