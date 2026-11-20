<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\StartPayment;
use App\Enums\OrderStatus;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\BrandPalette;
use App\Support\OutletContext;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrderController extends Controller
{
    /**
     * The live digital ticket. Anyone holding the order's UUID link may view
     * it; contact details are masked for everyone but the owner and staff.
     */
    public function show(Request $request, Order $order): Response
    {
        $order->load(OrderResource::relations());

        return Inertia::render('Orders/Show', [
            'order' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function invoice(Request $request, Order $order, Settings $settings): SymfonyResponse
    {
        $order->load(['items.modifiers', 'delivery', 'latestPayment', 'outlet']);
        $outlet = $settings->forOutlet($order->outlet);

        return Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'showContact' => OrderResource::showsContactDetails($request, $order),
            'brandColor' => BrandPalette::mix(app(BrandPalette::class)->primary(), '#000000', 0.10),
            'shop' => [
                'name' => $settings->string('shop.name'),
                'tagline' => $settings->string('shop.tagline'),
                'outlet' => app(OutletContext::class)->multi() ? $order->outlet->name : null,
                'address' => $outlet['shop.address'],
                'phone' => $outlet['shop.phone'],
            ],
        ])->download("invoice-{$order->business_date->format('Ymd')}-{$order->order_number}.pdf");
    }

    /**
     * Retry payment for an order that is still awaiting it.
     */
    public function pay(Order $order, StartPayment $startPayment): SymfonyResponse
    {
        return Inertia::location($startPayment->handle($order));
    }

    /**
     * Customers can back out only before paying; after that the shop decides.
     */
    public function cancel(Request $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $ownsOrder = ($request->user() && $request->user()->id === $order->user_id)
            || in_array($order->uuid, $request->session()->get('guest_orders', []), true);

        abort_unless($ownsOrder, 403);
        abort_unless($order->status === OrderStatus::PendingPayment, 409, 'This order can no longer be cancelled online.');

        $cancel->handle($order, 'Cancelled by customer', $request->user());

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} was cancelled.");
    }
}
