<?php

namespace App\Services\Messaging;

use App\Contracts\MessageChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use App\Support\Settings;
use Throwable;

/**
 * Composes and sends order updates to the customer, keeping a log of every
 * attempt in notification_logs.
 */
class OrderNotifier
{
    public function __construct(
        private readonly MessageChannel $channel,
        private readonly Settings $settings,
    ) {}

    public function statusChanged(Order $order, OrderStatus $status): void
    {
        if (! $this->settings->bool('notifications.enabled') || blank($order->customer_phone)) {
            return;
        }

        $order->loadMissing('delivery.rider.user');

        $message = $this->compose($order, $status);

        if ($message === null) {
            return;
        }

        $log = $order->notificationLogs()->make([
            'channel' => $this->channel->name(),
            'recipient' => $order->customer_phone,
            'template' => 'order_'.$status->value,
            'message' => $message,
        ]);

        try {
            $this->channel->send($order->customer_phone, $message);
            $log->fill(['status' => 'sent'])->save();
        } catch (Throwable $exception) {
            $log->fill(['status' => 'failed', 'error' => $exception->getMessage()])->save();
            report($exception);
        }
    }

    private function compose(Order $order, OrderStatus $status): ?string
    {
        $shop = $this->settings->string('shop.name');
        $number = $order->order_number;
        $trackUrl = route('orders.show', $order);

        return match ($status) {
            OrderStatus::Confirmed => sprintf(
                "%s: order %s confirmed ✅\nTotal %s (%s). Ready around %s.\nTrack it live: %s",
                $shop,
                $number,
                Money::format($order->total),
                $order->isPaid() ? 'paid' : 'pay at counter',
                $order->estimated_ready_at?->format('g:i A') ?? 'soon',
                $trackUrl,
            ),
            OrderStatus::Ready => match (true) {
                $order->isDelivery() => "{$shop}: order {$number} is packed and waiting for the rider 🛵",
                $order->isDineIn() => "{$shop}: order {$number} is ready and on its way to {$order->table_label} 🍔",
                default => sprintf(
                    "%s: order %s is READY 🍔\nShow pickup code %s at %s.",
                    $shop,
                    $number,
                    $order->pickup_code,
                    $this->settings->string('shop.counter'),
                ),
            },
            OrderStatus::OutForDelivery => sprintf(
                "%s: order %s is on the way 🛵%s\nTrack it live: %s",
                $shop,
                $number,
                $order->delivery?->rider ? ' Rider: '.$order->delivery->rider->user->name.'.' : '',
                $trackUrl,
            ),
            OrderStatus::Completed => "{$shop}: order {$number} completed. Thank you, enjoy your meal! 🙌",
            OrderStatus::Cancelled => trim(sprintf(
                '%s: order %s was cancelled. %s %s',
                $shop,
                $number,
                $order->cancel_reason ?? '',
                $order->payments()->where('status', 'refunded')->exists() ? 'Your payment is being refunded.' : '',
            )),
            default => null,
        };
    }
}
