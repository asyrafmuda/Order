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
        $dineIn = $order->isDineIn();

        // Online gateways confirm the order when they are paid; anything
        // settled at the till is done once the cashier has the money.
        $paidOnline = ! $order->isCounterSale() && $order->payment_method->isOnline();

        $steps = [
            self::step('placed', 'Order Placed', $order->isCounterSale() ? 'Counter' : 'Online Kiosk', $order->created_at),
            self::step(
                'payment',
                $paidOnline ? 'Payment Verified' : ($order->isPaid() ? 'Paid at Counter' : 'Pay at Counter'),
                $order->payment_method->label(),
                $paidOnline || $order->isPaid() ? $order->confirmed_at : null,
                done: $paidOnline ? $order->confirmed_at !== null : $order->isPaid(),
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
            self::step('kitchen', 'In the Kitchen', 'Cooking your order', $order->preparing_at, done: $order->ready_at !== null),
            // "Ready" stays the current step until the order is handed over.
            self::step(
                'ready',
                match (true) {
                    $delivery => 'Packed for Rider',
                    $dineIn => 'Ready to Serve',
                    default => 'Ready for Pickup',
                },
                match (true) {
                    $delivery => 'Waiting for rider',
                    $dineIn => 'Coming to '.($order->table_label ?? 'your table'),
                    default => 'Collect at counter',
                },
                $order->ready_at,
                done: $order->dispatched_at !== null || $order->completed_at !== null,
            ),
        ];

        if ($delivery) {
            $steps[] = self::step('dispatched', 'Out for Delivery', 'Rider on the way', $order->dispatched_at, done: $order->completed_at !== null);
        }

        $steps[] = self::step('completed', 'Order Fulfilled', $delivery ? 'Delivered' : ($dineIn ? 'Served' : 'Collected'), $order->completed_at);

        if ($order->status === OrderStatus::Cancelled) {
            return $steps;
        }

        // The first unfinished step is the one in progress. Paying at the
        // counter and a slow POS sync never hold up the kitchen, so those two
        // are skipped once the order has moved on without them.
        foreach ($steps as $index => $step) {
            if ($step['state'] === 'done') {
                continue;
            }

            $skippable = ($step['key'] === 'payment' && ! $paidOnline)
                || ($step['key'] === 'pos' && $order->preparing_at !== null);

            if (! $skippable) {
                $steps[$index]['state'] = 'active';

                break;
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
