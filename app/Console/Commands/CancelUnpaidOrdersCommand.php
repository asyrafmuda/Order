<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:cancel-unpaid {--minutes=30 : Cancel orders that have waited this long for payment}')]
#[Description('Cancel online orders that were never paid')]
class CancelUnpaidOrdersCommand extends Command
{
    public function handle(CancelOrder $cancel): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes((int) $this->option('minutes')))
            ->get();

        $orders->each(fn (Order $order) => $cancel->handle($order, 'Payment was not completed in time'));

        $this->components->info("Cancelled {$orders->count()} unpaid order(s).");

        return self::SUCCESS;
    }
}
