<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\OrderTimeline;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seesContact = self::showsContactDetails($request, $this->resource);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'pickup_code' => $this->pickup_code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'table_label' => $this->table_label,
            // The ticket shows where to collect: the order's own outlet, not
            // whichever outlet the viewer happens to be browsing.
            'outlet' => $this->whenLoaded('outlet', function () {
                $details = app(Settings::class)->forOutlet($this->outlet);

                return [
                    'id' => $this->outlet->id,
                    'name' => $this->outlet->name,
                    'address' => $details['shop.address'],
                    'phone' => $details['shop.phone'],
                    'whatsapp' => $details['shop.whatsapp'],
                    'maps_url' => $details['shop.maps_url'],
                    'counter' => $details['shop.counter'],
                ];
            }),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_final' => $this->status->isFinal(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'is_paid' => $this->isPaid(),
            'customer_name' => $this->customer_name,
            'customer_phone' => match (true) {
                blank($this->customer_phone) => null,
                $seesContact => $this->customer_phone,
                default => Phone::mask($this->customer_phone),
            },
            'customer_email' => $seesContact ? $this->customer_email : null,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'discount' => $this->discount,
            'tax_amount' => $this->tax_amount,
            'tax_rate' => $this->tax_rate,
            'tax_inclusive' => $this->tax_inclusive,
            'total' => $this->total,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'estimated_ready_at' => $this->estimated_ready_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'preparing_at' => $this->preparing_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'pos' => [
                'status' => $this->pos_sync_status->value,
                'receipt_number' => $this->pos_receipt_number,
                'synced_at' => $this->pos_synced_at?->toIso8601String(),
            ],
            'timeline' => OrderTimeline::for($this->resource),
            'can' => [
                'pay' => $this->status === OrderStatus::PendingPayment && $this->payment_method->isOnline(),
                'cancel' => $this->status === OrderStatus::PendingPayment,
            ],
            'items_count' => $this->whenLoaded('items', fn () => (int) $this->items->sum('quantity')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'code' => $item->product_code,
                'name' => $item->name,
                'variant_name' => $item->variant_name,
                'combo_name' => $item->combo_name,
                'combo_choice' => $item->combo_choice,
                'combo_price' => $item->combo_price,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'notes' => $item->notes,
                'kitchen_status' => $item->kitchen_status->value,
                'kitchen_status_label' => $item->kitchen_status->label(),
                'started_at' => $item->started_at?->toIso8601String(),
                'done_at' => $item->done_at?->toIso8601String(),
                'image_url' => $item->relationLoaded('product') ? $item->product?->image_url : null,
                'icon' => $item->relationLoaded('product') && $item->product?->relationLoaded('category')
                    ? $item->product->category?->icon
                    : null,
                'station' => $item->relationLoaded('station') && $item->station ? [
                    'id' => $item->station->id,
                    'name' => $item->station->name,
                ] : null,
                'modifiers' => $item->relationLoaded('modifiers')
                    ? $item->modifiers->map(fn ($modifier) => ['name' => $modifier->name, 'price' => $modifier->price])->all()
                    : [],
            ])->all()),
            'delivery' => $this->whenLoaded('delivery', fn () => $this->delivery ? [
                'id' => $this->delivery->id,
                'status' => $this->delivery->status->value,
                'status_label' => $this->delivery->status->label(),
                'recipient_name' => $this->delivery->recipient_name,
                'phone' => $seesContact ? $this->delivery->phone : Phone::mask($this->delivery->phone),
                'address' => $seesContact ? $this->delivery->full_address : $this->delivery->city,
                'notes' => $this->delivery->notes,
                'fee' => $this->delivery->fee,
                'zone' => $this->delivery->relationLoaded('zone') ? $this->delivery->zone?->name : null,
                'eta_minutes' => $this->delivery->relationLoaded('zone') ? $this->delivery->zone?->eta_minutes : null,
                'assigned_at' => $this->delivery->assigned_at?->toIso8601String(),
                'picked_up_at' => $this->delivery->picked_up_at?->toIso8601String(),
                'delivered_at' => $this->delivery->delivered_at?->toIso8601String(),
                'rider' => $this->delivery->relationLoaded('rider') && $this->delivery->rider?->relationLoaded('user') ? [
                    'id' => $this->delivery->rider->id,
                    'name' => $this->delivery->rider->user->name,
                    'phone' => $this->delivery->rider->user->phone,
                    'vehicle_type' => $this->delivery->rider->vehicle_type,
                    'plate_number' => $this->delivery->rider->plate_number,
                ] : null,
            ] : null),
            'payment' => $this->whenLoaded('latestPayment', fn () => $this->latestPayment ? [
                'reference' => $this->latestPayment->reference,
                'gateway' => $this->latestPayment->gateway,
                'method_label' => $this->latestPayment->method->label(),
                'status' => $this->latestPayment->status->value,
                'transaction_id' => $this->latestPayment->gateway_transaction_id,
                'paid_at' => $this->latestPayment->paid_at?->toIso8601String(),
            ] : null),
            'histories' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($history) => [
                'id' => $history->id,
                'status' => $history->status->value,
                'status_label' => $history->status->label(),
                'note' => $history->note,
                'by' => $history->relationLoaded('user') ? $history->user?->name : null,
                'at' => $history->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * Ticket links can be shared, so phone numbers and email are only shown
     * to staff, the account that placed the order, or the browser session
     * that placed (or looked up) it as a guest.
     */
    public static function showsContactDetails(Request $request, Order $order): bool
    {
        $viewer = $request->user();

        if ($viewer && ($viewer->isStaff() || $viewer->id === $order->user_id)) {
            return true;
        }

        return $request->hasSession()
            && in_array($order->uuid, $request->session()->get('guest_orders', []), true);
    }

    /**
     * Relations every full order view needs.
     *
     * @return array<int, string>
     */
    public static function relations(): array
    {
        return [
            'outlet',
            'items.modifiers',
            'items.product.category',
            'items.station',
            'delivery.zone',
            'delivery.rider.user',
            'latestPayment',
        ];
    }
}
