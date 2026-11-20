<?php

namespace App\Support;

use App\Enums\KitchenItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;

/**
 * A snapshot of how busy the kitchen is, used for the storefront status badge
 * and to estimate when a new order will be ready.
 */
class KitchenLoad
{
    /** @var array<int, array{level: string, label: string, active_orders: int, cooking_items: int, eta_min: int, eta_max: int}> */
    private array $snapshots = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly OutletContext $outlets,
    ) {}

    /**
     * @return array{level: string, label: string, active_orders: int, cooking_items: int, eta_min: int, eta_max: int}
     */
    public function snapshot(?Outlet $outlet = null): array
    {
        // Each outlet has its own kitchen and its own queue.
        $outlet ??= $this->outlets->current();

        if (isset($this->snapshots[$outlet->id])) {
            return $this->snapshots[$outlet->id];
        }

        $activeOrders = Order::query()->forOutlet($outlet)->inKitchen()->count();
        $cookingItems = OrderItem::query()
            ->where('kitchen_status', KitchenItemStatus::Cooking)
            ->whereHas('order', fn ($query) => $query->forOutlet($outlet)->inKitchen())
            ->count();

        [$level, $label, $delay] = match (true) {
            $activeOrders >= 12 => ['slammed', 'Very Busy', 20],
            $activeOrders >= 7 => ['busy', 'Busy', 10],
            $activeOrders >= 1 => ['normal', 'Normal', 0],
            default => ['quiet', 'Quiet', 0],
        };

        return $this->snapshots[$outlet->id] = [
            'level' => $level,
            'label' => $label,
            'active_orders' => $activeOrders,
            'cooking_items' => $cookingItems,
            'eta_min' => (int) $this->settings->forOutlet($outlet)['ordering.pickup_eta_min'] + $delay,
            'eta_max' => (int) $this->settings->forOutlet($outlet)['ordering.pickup_eta_max'] + $delay,
        ];
    }

    /**
     * Minutes from now a freshly confirmed order is expected to be ready.
     */
    public function estimatedPrepMinutes(?Outlet $outlet = null): int
    {
        return $this->snapshot($outlet)['eta_max'];
    }

    /**
     * Drop the remembered snapshots after the queue changed.
     */
    public function forget(): void
    {
        $this->snapshots = [];
    }
}
