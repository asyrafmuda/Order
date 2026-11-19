<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Messaging\OrderNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCustomerOfOrderStatus implements ShouldQueue
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->notifier->statusChanged($event->order, $event->to);
    }
}
