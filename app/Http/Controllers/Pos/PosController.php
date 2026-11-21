<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Orders\PlaceOrder;
use App\Data\CheckoutData;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PosOrderRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\Cart\CartPricer;
use App\Support\OutletPrices;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The counter POS: staff key in walk-in orders. The ticket is built in the
 * browser for speed and priced here on submit.
 */
class PosController extends Controller
{
    public function index(Request $request, Settings $settings, OutletPrices $prices): Response
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->whereHas('products', fn ($query) => $query->visible())
            ->with(['products' => fn ($query) => $query->visible()->ordered()->with(['variants', 'modifierGroups.modifiers'])])
            ->get();

        $prices->apply($categories->flatMap->products);

        $recent = Order::query()->forOutlet($this->outlet())
            ->where('source', OrderSource::Pos)
            ->where('business_date', now()->toDateString())
            ->latest('id')
            ->limit(6)
            ->get();

        return Inertia::render('Pos/Index', [
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'combo' => $prices->currentCombo()?->only(['id', 'name', 'description', 'price', 'choices']),
            'quickNotes' => array_values(array_filter((array) $settings->get('pos.quick_notes', []))),
            'autoPrint' => (bool) $settings->get('receipt.auto_print', false),
            'tables' => DiningTable::query()->forOutlet($this->outlet())->active()->ordered()->get(['id', 'name', 'seats']),
            'tax' => [
                'rate' => (float) $settings->get('tax.rate', 0),
                'inclusive' => $settings->bool('tax.inclusive'),
            ],
            'recent' => $recent->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'customer_name' => $order->table_label ?? $order->customer_name,
                'total' => $order->total,
                'status_label' => $order->status->label(),
                'is_paid' => $order->isPaid(),
            ]),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(PosOrderRequest $request, CartPricer $pricer, PlaceOrder $placeOrder): RedirectResponse
    {
        $lines = $request->lines();
        $invalid = [];
        $cart = $pricer->price($lines, OrderType::from($request->validated('type')), null, $invalid);

        // Nothing is silently dropped at the till: the cashier is told and fixes the ticket.
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'lines' => 'Some items on the ticket can no longer be sold as entered (sold out or an option changed). Remove or re-add them.',
            ]);
        }

        $paid = $request->boolean('paid');
        // "Pay later" is settled at hand-over, where the till takes it as cash.
        $method = $paid ? PaymentMethod::from($request->validated('payment_method')) : PaymentMethod::Cash;
        $tendered = $request->validated('tendered');

        if ($paid && $method === PaymentMethod::Cash && $tendered !== null && $tendered < $cart->total()) {
            throw ValidationException::withMessages(['tendered' => 'The cash received is less than the total.']);
        }

        $type = OrderType::from($request->validated('type'));
        $table = $type === OrderType::DineIn && $request->validated('dining_table_id')
            ? DiningTable::query()->forOutlet($this->outlet())->find($request->validated('dining_table_id'))
            : null;

        $order = $placeOrder->handle($cart, new CheckoutData(
            customerName: $request->validated('customer_name') ?: ($table?->name ?? 'Walk-in'),
            customerPhone: $request->validated('customer_phone'),
            customerEmail: null,
            paymentMethod: $method,
            notes: $request->validated('notes'),
            source: OrderSource::Pos,
            placedBy: $request->user(),
            paidAtCounter: $paid,
            cashTendered: $paid && $method === PaymentMethod::Cash ? $tendered : null,
            table: $table,
        ));

        return redirect()->route('pos.index')->with('receipt', [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'paid' => $paid,
            'method_label' => $method->label(),
            'change' => $paid && $method === PaymentMethod::Cash && $tendered !== null ? $tendered - $order->total : null,
        ]);
    }
}
