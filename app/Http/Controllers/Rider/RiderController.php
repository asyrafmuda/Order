<?php

namespace App\Http\Controllers\Rider;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\OrderItem;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rider's own run sheet.
 */
class RiderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rider = $this->rider($request);

        $open = $rider->deliveries()
            ->whereIn('status', [DeliveryStatus::Assigned, DeliveryStatus::PickedUp])
            ->with(['order.items.modifiers', 'zone'])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        $completed = $rider->deliveries()
            ->where('status', DeliveryStatus::Delivered)
            ->where('delivered_at', '>=', now()->startOfDay())
            ->with(['order.items.modifiers', 'zone'])
            ->latest('delivered_at')
            ->get();

        return Inertia::render('Rider/Index', [
            'rider' => [
                'id' => $rider->id,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'is_available' => $rider->is_available,
            ],
            'deliveries' => $open->map($this->present(...))->all(),
            'completed' => $completed->map($this->present(...))->all(),
        ]);
    }

    public function availability(Request $request): RedirectResponse
    {
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);

        $this->rider($request)->update(['is_available' => $validated['is_available']]);

        return back()->with('success', $validated['is_available']
            ? 'You are available for deliveries.'
            : 'You are off the roster. No new deliveries will be assigned.');
    }

    /**
     * Rider-role accounts created without a rider profile get one on first use.
     */
    private function rider(Request $request): Rider
    {
        return $request->user()->rider()->firstOrCreate([], [
            'vehicle_type' => 'Motorcycle',
            'is_available' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Delivery $delivery): array
    {
        $order = $delivery->order;

        return [
            'id' => $delivery->id,
            'status' => $delivery->status->value,
            'status_label' => $delivery->status->label(),
            'recipient_name' => $delivery->recipient_name,
            'phone' => $delivery->phone,
            'address' => $delivery->full_address,
            'notes' => $delivery->notes,
            'fee' => $delivery->fee,
            'zone' => $delivery->zone?->name,
            'eta_minutes' => $delivery->zone?->eta_minutes,
            'assigned_at' => $delivery->assigned_at?->toIso8601String(),
            'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'order' => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'customer_name' => $order->customer_name,
                'notes' => $order->notes,
                'total' => $order->total,
                'is_paid' => $order->isPaid(),
                'estimated_ready_at' => $order->estimated_ready_at?->toIso8601String(),
                'ready_at' => $order->ready_at?->toIso8601String(),
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'name' => $item->name,
                    'variant_name' => $item->variant_name,
                    'combo_name' => $item->combo_name,
                    'combo_choice' => $item->combo_choice,
                    'modifiers' => $item->modifiers->pluck('name')->all(),
                ])->all(),
            ],
        ];
    }
}
