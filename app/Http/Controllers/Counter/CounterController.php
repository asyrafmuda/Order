<?php

namespace App\Http\Controllers\Counter;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cashier's hand-over board: what is cooking, what can be collected and
 * which deliveries need a rider.
 */
class CounterController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $active = Order::query()->forOutlet($this->outlet())
            ->active()
            ->with(OrderResource::relations())
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();

        [$deliveries, $pickups] = $active->partition(fn (Order $order) => $order->isDelivery());
        [$ready, $cooking] = $pickups->partition(fn (Order $order) => $order->status === OrderStatus::Ready);

        return Inertia::render('Counter/Index', [
            'kitchenOrders' => $this->resolve($request, $cooking),
            'readyOrders' => $this->resolve($request, $ready),
            'deliveryOrders' => $this->resolve($request, $deliveries),
            'riders' => $this->riders(),
            'stats' => $this->stats($active),
            'selected' => $selected = $this->selected($request),
            'lookupFailed' => $selected === null && $request->hasAny(['order', 'code']),
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function resolve(Request $request, Collection $orders): array
    {
        return OrderResource::collection($orders->values())->resolve($request);
    }

    /**
     * The order opened in the hand-over panel: ?order={uuid}, or ?code=
     * {pickup code} when the customer's QR was scanned.
     *
     * @return array<string, mixed>|null
     */
    private function selected(Request $request): ?array
    {
        $uuid = $request->string('order')->trim()->toString();
        $code = $request->string('code')->trim()->upper()->toString();

        if ($uuid === '' && $code === '') {
            return null;
        }

        $order = Order::query()->forOutlet($this->outlet())
            ->with([...OrderResource::relations(), 'histories.user'])
            ->when($uuid !== '', fn ($query) => $query->where('uuid', $uuid), fn ($query) => $query->where('pickup_code', $code))
            ->first();

        return $order ? (new OrderResource($order))->resolve($request) : null;
    }

    /**
     * Riders that can be given a delivery, busiest last.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riders(): array
    {
        return Rider::query()->forOutlet($this->outlet())
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->withCount(['deliveries as open_deliveries_count' => fn ($query) => $query->whereIn('status', [
                DeliveryStatus::Assigned,
                DeliveryStatus::PickedUp,
            ])])
            ->orderByDesc('is_available')
            ->orderBy('open_deliveries_count')
            ->get()
            ->map(fn (Rider $rider) => [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'phone' => $rider->user->phone,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'is_available' => $rider->is_available,
                'open_deliveries' => $rider->open_deliveries_count,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $active
     * @return array<string, int>
     */
    private function stats(Collection $active): array
    {
        $unpaidCash = $active->filter(fn (Order $order) => $order->payment_method === PaymentMethod::Cash
            && $order->payment_status !== PaymentStatus::Paid);

        $today = Order::query()->forOutlet($this->outlet())->where('business_date', now()->toDateString());

        return [
            'active' => $active->count(),
            'completed_today' => (clone $today)->where('status', OrderStatus::Completed)->count(),
            'cash_to_collect' => (int) $unpaidCash->sum('total'),
            'cash_orders' => $unpaidCash->count(),
            'awaiting_rider' => $active
                ->filter(fn (Order $order) => $order->type === OrderType::Delivery && ! $order->delivery?->rider_id)
                ->count(),
        ];
    }
}
