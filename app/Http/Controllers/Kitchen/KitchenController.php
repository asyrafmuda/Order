<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The kitchen display: every ticket the crew still has to cook, oldest first.
 */
class KitchenController extends Controller
{
    private const RECENTLY_READY_MINUTES = 15;

    public function __invoke(Request $request): Response
    {
        $stations = KitchenStation::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $stationId = $stations->firstWhere('id', $request->integer('station'))?->id;

        $queue = Order::query()
            ->inKitchen()
            ->with(['items.modifiers', 'items.station'])
            ->when($stationId, fn ($query) => $query->whereHas(
                'items',
                fn ($items) => $items->where('kitchen_station_id', $stationId),
            ))
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();

        $ready = Order::query()
            ->where('status', OrderStatus::Ready)
            ->where('ready_at', '>=', now()->subMinutes(self::RECENTLY_READY_MINUTES))
            ->with(['items.modifiers', 'items.station'])
            ->latest('ready_at')
            ->limit(12)
            ->get();

        return Inertia::render('Kitchen/Index', [
            'orders' => OrderResource::collection($queue)->resolve($request),
            'ready' => OrderResource::collection($ready)->resolve($request),
            'stations' => $this->stationLoad($stations),
            'filters' => ['station' => $stationId],
        ]);
    }

    /**
     * Each station with the number of portions waiting and on the heat.
     *
     * @param  Collection<int, KitchenStation>  $stations
     * @return array<int, array{id: int, name: string, queued: int, cooking: int}>
     */
    private function stationLoad($stations): array
    {
        $counts = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->inKitchen())
            ->whereIn('kitchen_status', [KitchenItemStatus::Queued, KitchenItemStatus::Cooking])
            ->selectRaw('kitchen_station_id, kitchen_status, sum(quantity) as total')
            ->groupBy('kitchen_station_id', 'kitchen_status')
            ->toBase()
            ->get()
            ->groupBy('kitchen_station_id');

        return $stations->map(function (KitchenStation $station) use ($counts) {
            $load = $counts->get($station->id, collect())->pluck('total', 'kitchen_status');

            return [
                'id' => $station->id,
                'name' => $station->name,
                'queued' => (int) ($load[KitchenItemStatus::Queued->value] ?? 0),
                'cooking' => (int) ($load[KitchenItemStatus::Cooking->value] ?? 0),
            ];
        })->values()->all();
    }
}
