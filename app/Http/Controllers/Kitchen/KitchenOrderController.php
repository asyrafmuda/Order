<?php

namespace App\Http\Controllers\Kitchen;

use App\Actions\Kitchen\MarkOrderReady;
use App\Actions\Kitchen\UpdateKitchenItem;
use App\Enums\KitchenItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KitchenOrderController extends Controller
{
    /**
     * "Start all": put every waiting item of the ticket on the heat.
     */
    public function start(Request $request, Order $order, UpdateKitchenItem $update): RedirectResponse
    {
        $order->items()
            ->where('kitchen_status', KitchenItemStatus::Queued)
            ->get()
            ->each(fn (OrderItem $item) => $update->handle($item, KitchenItemStatus::Cooking, $request->user()));

        return back();
    }

    public function ready(Request $request, Order $order, MarkOrderReady $markReady): RedirectResponse
    {
        $order = $markReady->handle($order, $request->user());

        return back()->with('success', "Order {$order->order_number} is ready.");
    }
}
