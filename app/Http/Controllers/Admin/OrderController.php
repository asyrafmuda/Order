<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\CancelOrder;
use App\Actions\Pos\PushOrderToPos;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PosSyncLog;
use App\Support\Paginate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'date' => ['nullable', 'date'],
        ]);

        $orders = Order::query()
            ->with(['items', 'delivery'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.ltrim(trim($search), '#').'%';

                $query->where(fn ($query) => $query
                    ->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->where('business_date', date('Y-m-d', strtotime($date))))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => Paginate::through($orders, OrderResource::class),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
                'date' => $filters['date'] ?? '',
            ],
            'statuses' => OrderStatus::options(),
            'types' => array_map(fn (OrderType $type) => ['value' => $type->value, 'label' => $type->label()], OrderType::cases()),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load([...OrderResource::relations(), 'histories.user', 'posSyncLogs', 'notificationLogs']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => (new OrderResource($order))->resolve($request),
            'posLogs' => $order->posSyncLogs->sortByDesc('id')->values()->map(fn (PosSyncLog $log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'action' => $log->action,
                'status' => $log->status,
                'error' => $log->error,
                'at' => $log->created_at?->toIso8601String(),
            ]),
            'notifications' => $order->notificationLogs->sortByDesc('id')->values()->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'recipient' => $log->recipient,
                'template' => $log->template,
                'message' => $log->message,
                'status' => $log->status,
                'error' => $log->error,
                'at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $cancel->handle($order, $request->validated('reason'), $request->user());

        return back()->with('success', "Order {$order->order_number} was cancelled.");
    }

    public function resync(Order $order, PushOrderToPos $push): RedirectResponse
    {
        if ($order->status === OrderStatus::PendingPayment || $order->status === OrderStatus::Cancelled) {
            return back()->with('error', 'Only confirmed orders are sent to the POS.');
        }

        try {
            $order = $push->handle($order);
        } catch (PosException $exception) {
            return back()->with('error', 'POS sync failed: '.$exception->getMessage());
        }

        return back()->with('success', "Order {$order->order_number} is synced to the POS (receipt {$order->pos_receipt_number}).");
    }
}
