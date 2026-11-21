<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Everything a printed receipt needs, for staff screens to lay out on
 * thermal paper (58 or 80 mm) and send to the browser's printer.
 */
class ReceiptController extends Controller
{
    public function __invoke(Order $order, Settings $settings, OutletContext $outlets): JsonResponse
    {
        $order->load(['items.modifiers', 'delivery', 'latestPayment', 'outlet']);
        $outlet = $settings->forOutlet($order->outlet);
        $payment = $order->latestPayment;
        $tendered = $order->isPaid() ? ($payment?->payload['tendered'] ?? null) : null;

        return response()->json([
            'paper' => (string) $settings->get('receipt.paper', '80') === '58' ? '58' : '80',
            'kitchen_slip' => (bool) $settings->get('receipt.kitchen_slip', false),
            'shop' => [
                'name' => $settings->string('shop.name'),
                'outlet' => $outlets->multi() ? $order->outlet->name : null,
                'address' => $outlet['shop.address'],
                'phone' => $outlet['shop.phone'],
                'header' => $settings->get('receipt.header') ?: null,
                'footer' => $settings->get('receipt.footer') ?: null,
            ],
            'order' => [
                'uuid' => $order->uuid,
                'number' => $order->order_number,
                'placed_at' => $order->created_at?->toIso8601String(),
                'type_label' => $order->type->label(),
                'table' => $order->table_label,
                'customer_name' => $order->customer_name,
                'notes' => $order->notes,
                // Online orders are collected with the code; a counter sale has its number on the receipt.
                'pickup_code' => $order->delivery || $order->isCounterSale() ? null : $order->pickup_code,
                'pos_receipt_number' => $order->pos_receipt_number,
                'cancelled' => $order->cancelled_at !== null,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'quantity' => $item->quantity,
                'code' => $item->product_code,
                'name' => $item->name,
                'variant' => $item->variant_name,
                'modifiers' => $item->modifiers->pluck('name')->values(),
                'combo' => $item->combo_name ? $item->combo_name.($item->combo_choice ? ' ('.$item->combo_choice.')' : '') : null,
                'notes' => $item->notes,
                'line_total' => $item->line_total,
            ])->values(),
            'totals' => [
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'tax_rate' => (float) $order->tax_rate,
                'tax_inclusive' => (bool) $order->tax_inclusive,
                'tax_amount' => $order->tax_amount,
                'total' => $order->total,
            ],
            'payment' => [
                'paid' => $order->isPaid(),
                'method_label' => $order->payment_method->label(),
                'tendered' => $tendered,
                'change' => $tendered !== null ? max(0, $tendered - $order->total) : null,
                'transaction' => $payment?->gateway_transaction_id,
            ],
        ]);
    }
}
