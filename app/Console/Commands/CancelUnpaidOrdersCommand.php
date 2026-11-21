<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\ReconcilePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('orders:cancel-unpaid {--minutes=30 : Cancel orders that have waited this long for payment}')]
#[Description('Cancel online orders that were never paid')]
class CancelUnpaidOrdersCommand extends Command
{
    public function handle(CancelOrder $cancel, ReconcilePayment $reconcile): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes((int) $this->option('minutes')))
            ->get();

        $cancelled = 0;

        foreach ($orders as $order) {
            // The customer may have paid without coming back to the shop, and the
            // gateway's call may have been lost: ask the gateway before giving up.
            foreach ($order->payments()->where('status', PaymentStatus::Pending)->get() as $payment) {
                try {
                    $reconcile->handle($payment);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            if ($order->refresh()->status !== OrderStatus::PendingPayment) {
                continue;
            }

            $cancel->handle($order, 'Payment was not completed in time');
            $cancelled++;
        }

        $this->components->info("Cancelled {$cancelled} unpaid order(s).");

        return self::SUCCESS;
    }
}
