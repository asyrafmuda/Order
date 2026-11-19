import { Head, Link } from '@inertiajs/react';
import { Banknote, ClipboardList, Flame, Plug, ReceiptText, Trophy } from 'lucide-react';
import { StatCard } from '@/components/admin/StatCard';
import { OrderStatusBadge, PaymentStatusBadge } from '@/components/ui/Badge';
import { LinkButton } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { money, plural, time } from '@/lib/format';
import type { Order, OrderStatus } from '@/types';

interface DashboardProps {
    stats: { sales: number; orders: number; average: number; active: number; awaiting_payment: number; pos_failures: number };
    chart: { date: string; sales: number; orders: number }[];
    topProducts: { name: string; quantity: number; revenue: number }[];
    statuses: { value: OrderStatus; label: string; count: number }[];
    recent: Order[];
}

export default function Dashboard({ stats, chart, topProducts, statuses, recent }: DashboardProps) {
    const totalToday = statuses.reduce((sum, status) => sum + status.count, 0);

    return (
        <BackOfficeLayout
            title="Dashboard"
            description="Today at a glance"
            actions={
                <LinkButton href={route('admin.reports.index')} variant="outline" size="sm">
                    Full reports
                </LinkButton>
            }
        >
            <Head title="Dashboard" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Sales today" value={money(stats.sales)} tone="flame" icon={<Banknote className="size-4" />} hint="Paid orders, excluding cancelled" />
                <StatCard label="Paid orders" value={stats.orders} icon={<ReceiptText className="size-4" />} hint={`${totalToday} placed today in total`} />
                <StatCard label="Average ticket" value={money(stats.average)} icon={<Trophy className="size-4" />} />
                <StatCard
                    label="Active now"
                    value={stats.active}
                    tone={stats.active > 0 ? 'green' : 'default'}
                    icon={<Flame className="size-4" />}
                    hint={`${stats.awaiting_payment} awaiting payment`}
                />
                <StatCard
                    label="POS sync failures"
                    value={stats.pos_failures}
                    tone={stats.pos_failures > 0 ? 'red' : 'default'}
                    icon={<Plug className="size-4" />}
                    hint={
                        stats.pos_failures > 0 ? (
                            <Link href={route('admin.integrations.index')} className="font-semibold text-flame-400 hover:underline">
                                Review in Integrations
                            </Link>
                        ) : (
                            'All of today’s orders reached the POS'
                        )
                    }
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader title="Sales — last 14 days" icon={<Banknote className="size-4" />} />
                    <SalesChart data={chart} />
                </Card>

                <Card>
                    <CardHeader title="Top sellers — last 7 days" icon={<Trophy className="size-4" />} />
                    {topProducts.length === 0 ? (
                        <EmptyState title="No sales yet">Best sellers appear here once orders are paid.</EmptyState>
                    ) : (
                        <ol className="divide-y divide-ink-800">
                            {topProducts.map((product, index) => (
                                <li key={product.name} className="flex items-center gap-3 px-4 py-3">
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-ink-700 text-xs font-extrabold text-flame-400">{index + 1}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-semibold text-white">{product.name}</span>
                                        <span className="block text-xs text-ink-400">{money(product.revenue)}</span>
                                    </span>
                                    <span className="text-sm font-extrabold text-ink-100">×{product.quantity}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card>
                    <CardHeader title="Today by status" icon={<ClipboardList className="size-4" />} />
                    <ul className="divide-y divide-ink-800">
                        {statuses.map((status) => (
                            <li key={status.value}>
                                <Link
                                    href={route('admin.orders.index', { status: status.value })}
                                    className="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-ink-800"
                                >
                                    <OrderStatusBadge status={status.value} label={status.label} />
                                    <span className={cn('text-sm font-extrabold', status.count > 0 ? 'text-white' : 'text-ink-500')}>{status.count}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader
                        title="Latest orders"
                        icon={<ReceiptText className="size-4" />}
                        action={
                            <Link href={route('admin.orders.index')} className="text-xs font-semibold text-flame-400 hover:underline">
                                View all
                            </Link>
                        }
                    />
                    {recent.length === 0 ? (
                        <EmptyState title="No orders yet">New orders show up here as soon as they are placed.</EmptyState>
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Order</Th>
                                    <Th>Customer</Th>
                                    <Th>Status</Th>
                                    <Th>Payment</Th>
                                    <Th className="text-right">Total</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.map((order) => (
                                    <tr key={order.uuid} className="hover:bg-ink-800/60">
                                        <Td>
                                            <Link href={route('admin.orders.show', order.uuid)} className="font-extrabold text-white hover:text-flame-400">
                                                #{order.order_number}
                                            </Link>
                                            <span className="block text-xs text-ink-400">
                                                {time(order.created_at)} · {order.type_label}
                                            </span>
                                        </Td>
                                        <Td>
                                            {order.customer_name}
                                            <span className="block text-xs text-ink-400">{plural(order.items_count ?? 0, 'item')}</span>
                                        </Td>
                                        <Td>
                                            <OrderStatusBadge status={order.status} label={order.status_label} />
                                        </Td>
                                        <Td>
                                            <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                                        </Td>
                                        <Td className="text-right font-bold text-flame-400">{money(order.total)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>
            </div>
        </BackOfficeLayout>
    );
}

function SalesChart({ data }: { data: DashboardProps['chart'] }) {
    const max = Math.max(...data.map((day) => day.sales), 1);
    const total = data.reduce((sum, day) => sum + day.sales, 0);

    return (
        <div className="p-4">
            <p className="text-xs text-ink-400">
                <strong className="text-sm font-extrabold text-white">{money(total)}</strong> across {plural(data.reduce((sum, day) => sum + day.orders, 0), 'paid order')}
            </p>
            <div className="mt-4 flex h-44 items-end gap-1.5" role="img" aria-label="Daily sales for the last 14 days">
                {data.map((day, index) => {
                    const isToday = index === data.length - 1;
                    const label = new Date(`${day.date}T00:00:00`).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });

                    return (
                        <div key={day.date} className="group relative flex h-full flex-1 flex-col justify-end" title={`${label}: ${money(day.sales)} · ${plural(day.orders, 'order')}`}>
                            <span className="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full rounded-md bg-ink-700 px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-white shadow-lg group-hover:block">
                                {money(day.sales)} · {day.orders}
                            </span>
                            <div
                                className={cn(
                                    'w-full rounded-t-md transition-colors',
                                    day.sales === 0 ? 'bg-ink-700' : isToday ? 'bg-flame-400' : 'bg-brand-600 group-hover:bg-brand-500',
                                )}
                                style={{ height: `${Math.max((day.sales / max) * 100, 2)}%` }}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex gap-1.5">
                {data.map((day, index) => (
                    <span key={day.date} className={cn('flex-1 text-center text-[10px]', index === data.length - 1 ? 'font-bold text-flame-400' : 'text-ink-500')}>
                        {new Date(`${day.date}T00:00:00`).getDate()}
                    </span>
                ))}
            </div>
        </div>
    );
}
