<?php

namespace App\Http\Controllers;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SyncStatus;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Kitchen & POS Status" board: queue numbers only, no customer data.
 */
class KitchenStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $board = Order::query()
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready])
            ->orderBy('confirmed_at')
            ->get(['id', 'order_number', 'status', 'type', 'confirmed_at', 'estimated_ready_at', 'ready_at']);

        $itemCounts = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->inKitchen())
            ->selectRaw('kitchen_station_id, kitchen_status, sum(quantity) as total')
            ->groupBy('kitchen_station_id', 'kitchen_status')
            ->get()
            ->groupBy('kitchen_station_id');

        $stations = KitchenStation::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(function (KitchenStation $station) use ($itemCounts) {
                $counts = $itemCounts->get($station->id, collect())->pluck('total', 'kitchen_status.value');

                return [
                    'id' => $station->id,
                    'name' => $station->name,
                    'queued' => (int) ($counts[KitchenItemStatus::Queued->value] ?? 0),
                    'cooking' => (int) ($counts[KitchenItemStatus::Cooking->value] ?? 0),
                    'done' => (int) ($counts[KitchenItemStatus::Done->value] ?? 0),
                ];
            });

        $today = Order::query()->where('business_date', now()->toDateString());

        return Inertia::render('Status/Index', [
            'board' => $board->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'type' => $order->type->value,
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'estimated_ready_at' => $order->estimated_ready_at?->toIso8601String(),
            ])->values(),
            'stations' => $stations->values(),
            'sync' => [
                'synced_today' => (clone $today)->where('pos_sync_status', SyncStatus::Synced)->count(),
                'failed_today' => (clone $today)->where('pos_sync_status', SyncStatus::Failed)->count(),
                'completed_today' => (clone $today)->where('status', OrderStatus::Completed)->count(),
                'last_synced_at' => Order::query()->whereNotNull('pos_synced_at')->latest('pos_synced_at')->first()
                    ?->pos_synced_at->toIso8601String(),
            ],
        ]);
    }
}
