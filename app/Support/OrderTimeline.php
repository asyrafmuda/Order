<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\SyncStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Builds the "Live Order Lifecycle" steps shown on the digital ticket.
 */
final class OrderTimeline
{
    /**
     * @return array<int, array{key: string, label: string, detail: string, state: string, at: ?string}>
     */
    public static function for(Order $order): array
    {
        $delivery = $order->isDelivery();
        $cancelled = $order->status === OrderStatus::Cancelled;

        $steps = [
            self::step('placed', 'Order Placed', 'Online Kiosk', $order->created_at),
            self::step(
                'payment',
                $order->payment_method->isOnline() ? 'Payment Verified' : 'Pay at Counter',
                $order->payment_method->label(),
                $order->payment_method->isOnline() ? $order->confirmed_at : ($order->isPaid() ? $order->confirmed_at : null),
                done: $order->payment_method->isOnline() ? $order->confirmed_at !== null : $order->isPaid(),
            ),
            self::step(
                'pos',
                'POS Synced',
                $order->pos_receipt_number ? 'Ticket #'.$order->pos_receipt_number : match ($order->pos_sync_status) {
                    SyncStatus::Failed => 'Sync retrying',
                    default => 'Waiting for POS',
                },
                $order->pos_synced_at,
            ),
            self::step('kitchen', 'Grill & Station', 'Cooking your order', $order->preparing_at, done: $order->ready_at !== null),
            self::step('ready', $delivery ? 'Packed for Rider' : 'Ready for Pickup', $delivery ? 'Waiting for rider' : 'Collect at counter', $order->ready_at),
        ];

        if ($delivery) {
            $steps[] = self::step('dispatched', 'Out for Delivery', 'Rider on the way', $order->dispatched_at);
        }

        $steps[] = self::step('completed', 'Order Fulfilled', $delivery ? 'Delivered' : 'Collected', $order->completed_at);

        // The first step that is not done yet is the one in progress.
        $activeMarked = false;

        foreach ($steps as $index => $step) {
            if ($step['state'] === 'done') {
                continue;
            }

            if (! $activeMarked && ! $cancelled && ! ($step['key'] === 'payment' && ! $order->payment_method->isOnline())) {
                $steps[$index]['state'] = 'active';
                $activeMarked = true;
            }
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, detail: string, state: string, at: ?string}
     */
    private static function step(string $key, string $label, string $detail, ?CarbonInterface $at, ?bool $done = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'state' => ($done ?? $at !== null) ? 'done' : 'upcoming',
            'at' => $at?->toIso8601String(),
        ];
    }
}
