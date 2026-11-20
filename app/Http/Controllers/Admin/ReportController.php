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
use App\Models\Outlet;
use App\Support\OutletContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales reports. Every figure counts paid orders that were not cancelled.
 *
 * A report covers the outlet being worked in. A super admin of a business
 * with several outlets can also ask for all of them together (?outlet=all).
 */
class ReportController extends Controller
{
    public function index(ReportRequest $request, OutletContext $outlets): Response
    {
        [$from, $to] = $request->period();
        $all = $request->allOutlets();

        $totals = $this->sales($from, $to, $all)
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as sales, coalesce(sum(delivery_fee), 0) as delivery_fees, coalesce(sum(tax_amount), 0) as tax')
            ->toBase()
            ->first();

        return Inertia::render('Admin/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'outlet' => $all ? 'all' : ''],
            'scope' => [
                'all' => $all,
                'can_all' => $outlets->multi() && $request->user()->isAdmin(),
                'outlet' => $this->outlet()->name,
            ],
            // The shop's "today", so quick ranges do not depend on the browser's time zone.
            'today' => CarbonImmutable::today()->toDateString(),
            'totals' => [
                'sales' => (int) $totals->sales,
                'orders' => (int) $totals->orders,
                'average' => $totals->orders > 0 ? (int) round($totals->sales / $totals->orders) : 0,
                'delivery_fees' => (int) $totals->delivery_fees,
                'tax' => (int) $totals->tax,
            ],
            'byOutlet' => $all ? $this->byOutlet($from, $to) : [],
            'byDay' => $this->sales($from, $to, $all)
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
                ->when(! $all, fn ($query) => $query->where('orders.outlet_id', $this->outlet()->id))
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
            'byPaymentMethod' => $this->breakdown($from, $to, $all, 'payment_method', fn (string $value) => PaymentMethod::from($value)->label()),
            'byType' => $this->breakdown($from, $to, $all, 'type', fn (string $value) => OrderType::from($value)->label()),
        ]);
    }

    /**
     * One CSV row per order in the period. Amounts are in ringgit. The
     * all-outlets export says which outlet each order belongs to.
     */
    public function export(ReportRequest $request): StreamedResponse
    {
        [$from, $to] = $request->period();
        $all = $request->allOutlets();
        $filename = 'sales-'.($all ? 'all-outlets-' : '')."{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($from, $to, $all) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                ...($all ? ['Outlet'] : []),
                'Order', 'Date', 'Time', 'Customer', 'Phone', 'Type', 'Payment method', 'Items', 'Subtotal (RM)',
                'Delivery fee (RM)', 'SST (RM)', 'Total (RM)', 'Status', 'POS receipt',
            ]);

            $this->sales($from, $to, $all)->with('outlet')->withSum('items', 'quantity')->orderBy('id')->chunk(500, function ($orders) use ($out, $all) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        ...($all ? [$order->outlet->name] : []),
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
    private function sales(CarbonImmutable $from, CarbonImmutable $to, bool $all): Builder
    {
        return Order::query()
            ->when(! $all, fn (Builder $query) => $query->forOutlet($this->outlet()))
            ->paid()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Every outlet side by side, best seller first. Outlets that sold nothing
     * in the period are listed too, so a quiet branch is not simply missing.
     *
     * @return array<int, array{id: int, name: string, is_active: bool, orders: int, sales: int, average: int}>
     */
    private function byOutlet(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($from, $to, true)
            ->selectRaw('outlet_id, count(*) as orders, sum(total) as sales')
            ->groupBy('outlet_id')
            ->toBase()
            ->get()
            ->keyBy('outlet_id');

        return Outlet::query()->ordered()->get()
            ->map(fn (Outlet $outlet) => [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'is_active' => $outlet->is_active,
                'orders' => $orders = (int) ($sales[$outlet->id]->orders ?? 0),
                'sales' => $total = (int) ($sales[$outlet->id]->sales ?? 0),
                'average' => $orders > 0 ? (int) round($total / $orders) : 0,
            ])
            ->sortByDesc('sales')
            ->values()
            ->all();
    }

    /**
     * Orders and sales grouped by one column.
     *
     * @param  callable(string): string  $label
     * @return array<int, array{value: string, label: string, orders: int, sales: int}>
     */
    private function breakdown(CarbonImmutable $from, CarbonImmutable $to, bool $all, string $column, callable $label): array
    {
        return $this->sales($from, $to, $all)
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
