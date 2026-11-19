<?php

namespace App\Support;

use App\Enums\KitchenItemStatus;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * A snapshot of how busy the kitchen is, used for the storefront status badge
 * and to estimate when a new order will be ready.
 */
class KitchenLoad
{
    /** @var array{level: string, label: string, active_orders: int, cooking_items: int, eta_min: int, eta_max: int}|null */
    private ?array $snapshot = null;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{level: string, label: string, active_orders: int, cooking_items: int, eta_min: int, eta_max: int}
     */
    public function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $activeOrders = Order::query()->inKitchen()->count();
        $cookingItems = OrderItem::query()
            ->where('kitchen_status', KitchenItemStatus::Cooking)
            ->whereHas('order', fn ($query) => $query->inKitchen())
            ->count();

        [$level, $label, $delay] = match (true) {
            $activeOrders >= 12 => ['slammed', 'Very Busy', 20],
            $activeOrders >= 7 => ['busy', 'Busy', 10],
            $activeOrders >= 1 => ['normal', 'Normal', 0],
            default => ['quiet', 'Quiet', 0],
        };

        return $this->snapshot = [
            'level' => $level,
            'label' => $label,
            'active_orders' => $activeOrders,
            'cooking_items' => $cookingItems,
            'eta_min' => $this->settings->int('ordering.pickup_eta_min') + $delay,
            'eta_max' => $this->settings->int('ordering.pickup_eta_max') + $delay,
        ];
    }

    /**
     * Minutes from now a freshly confirmed order is expected to be ready.
     */
    public function estimatedPrepMinutes(): int
    {
        return $this->snapshot()['eta_max'];
    }
}
