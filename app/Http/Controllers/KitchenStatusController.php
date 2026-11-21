<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Kitchen Status" board: queue numbers only. No customer data, and no
 * internal detail (stations, POS connection): staff see those in the kitchen app.
 */
class KitchenStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $board = Order::query()->forOutlet($this->outlet())
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready])
            ->orderBy('confirmed_at')
            ->get(['id', 'order_number', 'status', 'type', 'confirmed_at', 'estimated_ready_at', 'ready_at']);

        return Inertia::render('Status/Index', [
            'board' => $board->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'type' => $order->type->value,
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'estimated_ready_at' => $order->estimated_ready_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
