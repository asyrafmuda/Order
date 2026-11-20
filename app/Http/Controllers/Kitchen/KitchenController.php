<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\OutletStock;
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

        $queue = Order::query()->forOutlet($this->outlet())
            ->inKitchen()
            ->with(['items.modifiers', 'items.station'])
            ->when($stationId, fn ($query) => $query->whereHas(
                'items',
                fn ($items) => $items->where('kitchen_station_id', $stationId),
            ))
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();

        $ready = Order::query()->forOutlet($this->outlet())
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
            'menu' => $this->menu(),
        ]);
    }

    /**
     * The menu for the "sold out" panel, grouped by category.
     *
     * @return array<int, array{name: string, icon: ?string, products: array<int, array<string, mixed>>}>
     */
    private function menu(): array
    {
        $stock = app(OutletStock::class);

        return Category::query()
            ->active()
            ->ordered()
            ->with(['products' => fn ($query) => $query->visible()->ordered()])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'icon' => $category->icon,
                'products' => $category->products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    // "sold_out" here means sold out at this outlet (the crew can undo it);
                    // "locked" items are switched off for the whole business by an admin.
                    'status' => match (true) {
                        $product->status !== ProductStatus::Available => 'locked',
                        $stock->isSoldOut($product) => 'sold_out',
                        default => 'available',
                    },
                    'status_label' => $product->status === ProductStatus::Available ? ProductStatus::Available->label() : $product->status->label(),
                ])->all(),
            ])
            ->values()
            ->all();
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
            ->whereHas('order', fn ($query) => $query->forOutlet($this->outlet())->inKitchen())
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
