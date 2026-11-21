<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Installer\Installation;
use App\Support\Installer\SchedulerHeartbeat;
use App\Support\Licensing\License;
use App\Support\Licensing\LicenseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $today = CarbonImmutable::today();

        $todaySales = $this->sales()
            ->where('business_date', $today->toDateString())
            ->selectRaw('coalesce(sum(total), 0) as sales, count(*) as orders')
            ->toBase()
            ->first();

        $statusCounts = Order::query()->forOutlet($this->outlet())
            ->where('business_date', $today->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $recent = Order::query()->forOutlet($this->outlet())->with('items')->latest()->limit(8)->get();

        $heartbeat = app(SchedulerHeartbeat::class);
        $license = app(License::class)->status($request->getHost());

        return Inertia::render('Admin/Dashboard', [
            // Super admins are told when the cron job that sends messages and
            // POS receipts is not running.
            'cronMissing' => $request->user()->isAdmin() && $heartbeat->isMissing(app(Installation::class))
                ? ['last_run' => $heartbeat->lastRun()?->toIso8601String(), 'command' => 'php '.base_path('artisan').' schedule:run >> /dev/null 2>&1']
                : null,
            // The license server has not answered for a while; the site stops if it goes on.
            'licenseWarning' => $request->user()->isAdmin() && $license === LicenseStatus::Warning ? $license->help() : null,
            'stats' => [
                'sales' => (int) $todaySales->sales,
                'orders' => (int) $todaySales->orders,
                'average' => $todaySales->orders > 0 ? (int) round($todaySales->sales / $todaySales->orders) : 0,
                'active' => Order::query()->forOutlet($this->outlet())->active()->count(),
                'awaiting_payment' => Order::query()->forOutlet($this->outlet())->where('status', OrderStatus::PendingPayment)->count(),
                'pos_failures' => Order::query()->forOutlet($this->outlet())
                    ->where('business_date', $today->toDateString())
                    ->where('pos_sync_status', SyncStatus::Failed)
                    ->count(),
            ],
            'chart' => $this->chart($today),
            'topProducts' => $this->topProducts($today->subDays(6)),
            'statuses' => array_map(fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ], OrderStatus::cases()),
            'recent' => OrderResource::collection($recent)->resolve($request),
        ]);
    }

    /**
     * Daily paid sales for the last 14 days, including days with no orders.
     *
     * @return array<int, array{date: string, sales: int, orders: int}>
     */
    private function chart(CarbonImmutable $today): array
    {
        $from = $today->subDays(13);

        $rows = $this->sales()
            ->where('business_date', '>=', $from->toDateString())
            ->selectRaw('business_date, sum(total) as sales, count(*) as orders')
            ->groupBy('business_date')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->business_date, 0, 10));

        return collect(range(0, 13))->map(function (int $offset) use ($from, $rows) {
            $date = $from->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'sales' => (int) ($rows[$date]->sales ?? 0),
                'orders' => (int) ($rows[$date]->orders ?? 0),
            ];
        })->all();
    }

    /**
     * @return array<int, array{name: string, quantity: int, revenue: int}>
     */
    private function topProducts(CarbonImmutable $from): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.outlet_id', $this->outlet()->id)
            ->where('orders.payment_status', PaymentStatus::Paid)
            ->where('orders.status', '!=', OrderStatus::Cancelled)
            ->where('orders.business_date', '>=', $from->toDateString())
            ->selectRaw('order_items.name as name, sum(order_items.quantity) as quantity, sum(order_items.line_total) as revenue')
            ->groupBy('order_items.name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Orders that count as sales: paid and not cancelled.
     *
     * @return Builder<Order>
     */
    private function sales()
    {
        return Order::query()->forOutlet($this->outlet())->paid()->where('status', '!=', OrderStatus::Cancelled);
    }
}
