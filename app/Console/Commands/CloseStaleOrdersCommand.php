<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:close-stale {--hours= : Override the configured waiting time}')]
#[Description('Close ready pickup orders that were never marked as collected')]
class CloseStaleOrdersCommand extends Command
{
    /**
     * Paid orders are assumed collected (the cashier forgot to tap "hand
     * over"); unpaid ones are no-shows and are cancelled. Deliveries are
     * left alone — a rider is responsible for those.
     */
    public function handle(Settings $settings, TransitionOrder $transition, CancelOrder $cancel): int
    {
        $hours = (int) ($this->option('hours') ?? $settings->int('ordering.auto_close_hours'));

        if ($hours < 1) {
            $this->components->info('Auto-close is switched off.');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('status', OrderStatus::Ready)
            ->where('type', '!=', OrderType::Delivery)
            ->where('ready_at', '<', now()->subHours($hours))
            ->get();

        [$paid, $unpaid] = $orders->partition(fn (Order $order) => $order->isPaid());

        $paid->each(fn (Order $order) => $transition->handle(
            $order,
            OrderStatus::Completed,
            note: "Closed automatically after {$hours}h — not marked as collected",
        ));

        $unpaid->each(fn (Order $order) => $cancel->handle($order, 'Not collected'));

        $this->components->info("Completed {$paid->count()} and cancelled {$unpaid->count()} uncollected order(s).");

        return self::SUCCESS;
    }
}
