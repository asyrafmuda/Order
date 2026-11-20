<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales reports. Every figure counts paid orders that were not cancelled.
 */
class ReportController extends Controller
{
    public function index(ReportRequest $request): Response
    {
        [$from, $to] = $request->period();

        $totals = $this->sales($from, $to)
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as sales, coalesce(sum(delivery_fee), 0) as delivery_fees, coalesce(sum(tax_amount), 0) as tax')
            ->toBase()
            ->first();

        return Inertia::render('Admin/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            // The shop's "today", so quick ranges do not depend on the browser's time zone.
            'today' => CarbonImmutable::today()->toDateString(),
            'totals' => [
                'sales' => (int) $totals->sales,
                'orders' => (int) $totals->orders,
                'average' => $totals->orders > 0 ? (int) round($totals->sales / $totals->orders) : 0,
                'delivery_fees' => (int) $totals->delivery_fees,
                'tax' => (int) $totals->tax,
            ],
            'byDay' => $this->sales($from, $to)
                ->selectRaw('business_date, count(*) as orders, sum(total) as sales')
                ->groupBy('business_date')
                ->orderBy('business_date')
                ->toBase()
                ->get()
                ->map(fn ($row) => [
                    'date' => substr((string) $row->business_date, 0, 10),
                    'orders' => (int) $row->orders,
                    'sales' => (int) $row->sales,
                ]),
            'byProduct' => OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.outlet_id', $this->outlet()->id)
                ->where('orders.payment_status', PaymentStatus::Paid)
                ->where('orders.status', '!=', OrderStatus::Cancelled)
                ->whereBetween('orders.business_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('order_items.name as name, max(order_items.product_code) as code, sum(order_items.quantity) as quantity, sum(order_items.line_total) as revenue')
                ->groupBy('order_items.name')
                ->orderByDesc('revenue')
                ->limit(50)
                ->toBase()
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'code' => $row->code,
                    'quantity' => (int) $row->quantity,
                    'revenue' => (int) $row->revenue,
                ]),
            'byPaymentMethod' => $this->breakdown($from, $to, 'payment_method', fn (string $value) => PaymentMethod::from($value)->label()),
            'byType' => $this->breakdown($from, $to, 'type', fn (string $value) => OrderType::from($value)->label()),
        ]);
    }

    /**
     * One CSV row per order in the period. Amounts are in ringgit.
     */
    public function export(ReportRequest $request): StreamedResponse
    {
        [$from, $to] = $request->period();
        $filename = "sales-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Order', 'Date', 'Time', 'Customer', 'Phone', 'Type', 'Payment method', 'Items', 'Subtotal (RM)',
                'Delivery fee (RM)', 'SST (RM)', 'Total (RM)', 'Status', 'POS receipt',
            ]);

            $this->sales($from, $to)->withSum('items', 'quantity')->orderBy('id')->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->order_number,
                        $order->business_date->toDateString(),
                        $order->created_at->format('H:i'),
                        $order->customer_name,
                        $order->customer_phone,
                        $order->type->label(),
                        $order->payment_method->label(),
                        (int) $order->items_sum_quantity,
                        number_format($order->subtotal / 100, 2, '.', ''),
                        number_format($order->delivery_fee / 100, 2, '.', ''),
                        number_format($order->tax_amount / 100, 2, '.', ''),
                        number_format($order->total / 100, 2, '.', ''),
                        $order->status->label(),
                        $order->pos_receipt_number,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<Order>
     */
    private function sales(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()->forOutlet($this->outlet())
            ->paid()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Orders and sales grouped by one column.
     *
     * @param  callable(string): string  $label
     * @return array<int, array{value: string, label: string, orders: int, sales: int}>
     */
    private function breakdown(CarbonImmutable $from, CarbonImmutable $to, string $column, callable $label): array
    {
        return $this->sales($from, $to)
            ->selectRaw("{$column} as value, count(*) as orders, sum(total) as sales")
            ->groupBy($column)
            ->orderByDesc('sales')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'value' => $row->value,
                'label' => $label($row->value),
                'orders' => (int) $row->orders,
                'sales' => (int) $row->sales,
            ])
            ->all();
    }
}
