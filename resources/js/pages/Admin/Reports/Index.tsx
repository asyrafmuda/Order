import { Head, router } from '@inertiajs/react';
import { BarChart3, Building2, CalendarRange, Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/admin/StatCard';
import { Button, buttonClass } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Input } from '@/components/ui/Form';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { money, plural } from '@/lib/format';

interface Breakdown {
    value: string;
    label: string;
    orders: number;
    sales: number;
}

interface Filters {
    from: string;
    to: string;
    /** "all" for every outlet together, "" for the outlet being viewed. */
    outlet: string;
}

interface ReportsProps {
    filters: Filters;
    scope: { all: boolean; can_all: boolean; outlet: string };
    byOutlet: { id: number; name: string; is_active: boolean; orders: number; sales: number; average: number }[];
    /** Today's date at the shop (Y-m-d). */
    today: string;
    totals: { sales: number; orders: number; average: number; delivery_fees: number; tax: number };
    byDay: { date: string; orders: number; sales: number }[];
    byProduct: { name: string; code: string | null; quantity: number; revenue: number }[];
    byPaymentMethod: Breakdown[];
    byType: Breakdown[];
}

/** Shift a Y-m-d date by whole days without involving the browser's time zone. */
const shiftDate = (date: string, days: number) => {
    const shifted = new Date(`${date}T00:00:00Z`);
    shifted.setUTCDate(shifted.getUTCDate() + days);

    return shifted.toISOString().slice(0, 10);
};

const presets = [
    { label: 'Today', days: 0 },
    { label: '7 days', days: 6 },
    { label: '30 days', days: 29 },
    { label: '90 days', days: 89 },
];

export default function ReportsIndex({ filters, scope, today, totals, byOutlet, byDay, byProduct, byPaymentMethod, byType }: ReportsProps) {
    const [range, setRange] = useState(filters);
    const [loading, setLoading] = useState(false);
    const maxDay = Math.max(...byDay.map((day) => day.sales), 1);

    // Leave "outlet" out of the address unless every outlet is being reported on.
    const query = ({ outlet, ...dates }: Filters) => (outlet ? { ...dates, outlet } : dates);

    const apply = (next: Filters) => {
        setRange(next);
        router.get(route('admin.reports.index'), query(next), { preserveScroll: true, preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply(range);
    };

    const preset = (days: number) => apply({ ...range, from: shiftDate(today, -days), to: today });

    return (
        <BackOfficeLayout
            title="Reports"
            description={`${scope.can_all ? (scope.all ? 'All outlets' : scope.outlet) + ' · ' : ''}Paid orders, excluding cancelled ones`}
            actions={
                <a href={route('admin.reports.export', query(filters))} className={buttonClass('outline', 'sm')}>
                    <Download className="size-4" aria-hidden /> Export CSV
                </a>
            }
        >
            <Head title="Reports" />

            <Card className="mb-6 p-4">
                {scope.can_all && (
                    <div className="mb-4 flex rounded-lg border border-ink-700 bg-ink-900 p-1 sm:inline-flex" role="group" aria-label="Outlets in this report">
                        {[
                            { value: '', label: scope.outlet },
                            { value: 'all', label: 'All outlets' },
                        ].map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={range.outlet === option.value}
                                onClick={() => apply({ ...range, outlet: option.value })}
                                className={cn(
                                    'min-w-0 flex-1 truncate rounded-md px-3 py-1.5 text-sm font-bold transition-colors sm:flex-none',
                                    range.outlet === option.value ? 'bg-brand-600 text-white' : 'text-ink-300 hover:text-strong',
                                )}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                )}
                <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                    <Input label="From" type="date" required max={range.to} value={range.from} onChange={(event) => setRange({ ...range, from: event.target.value })} />
                    <Input label="To" type="date" required min={range.from} max={today} value={range.to} onChange={(event) => setRange({ ...range, to: event.target.value })} />
                    <Button type="submit" loading={loading} icon={<CalendarRange className="size-4" aria-hidden />}>
                        Apply
                    </Button>
                    <div className="ml-auto flex flex-wrap gap-1.5">
                        {presets.map((item) => (
                            <Button key={item.label} variant="secondary" size="sm" onClick={() => preset(item.days)}>
                                {item.label}
                            </Button>
                        ))}
                    </div>
                </form>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                <StatCard label="Gross sales" value={money(totals.sales)} tone="flame" />
                <StatCard label="Orders" value={totals.orders} />
                <StatCard label="Average ticket" value={money(totals.average)} />
                <StatCard label="Delivery fees" value={money(totals.delivery_fees)} hint="Included in gross sales" />
                <StatCard label="SST collected" value={money(totals.tax)} />
            </div>

            {totals.orders === 0 ? (
                <Card className="mt-6">
                    <EmptyState icon={<BarChart3 className="size-8" />} title="No paid orders in this period">
                        Pick a wider date range.
                    </EmptyState>
                </Card>
            ) : (
                <>
                    {scope.all && (
                        <Card className="mt-6">
                            <CardHeader title="Sales by outlet" icon={<Building2 className="size-4" />} />
                            <Table>
                                <thead>
                                    <tr>
                                        <Th>Outlet</Th>
                                        <Th className="text-right">Orders</Th>
                                        <Th className="hidden text-right sm:table-cell">Average ticket</Th>
                                        <Th className="text-right">Sales</Th>
                                        <Th className="hidden w-1/4 md:table-cell" aria-label="Share" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {byOutlet.map((row) => {
                                        const share = totals.sales > 0 ? Math.round((row.sales / totals.sales) * 100) : 0;

                                        return (
                                            <tr key={row.id}>
                                                <Td>
                                                    <span className="font-semibold text-strong">{row.name}</span>
                                                    {!row.is_active && <span className="ml-2 text-xs text-ink-400">inactive</span>}
                                                </Td>
                                                <Td className="text-right">{row.orders}</Td>
                                                <Td className="hidden text-right whitespace-nowrap sm:table-cell">{row.orders > 0 ? money(row.average) : '–'}</Td>
                                                <Td className="text-right font-bold whitespace-nowrap text-flame-400">
                                                    {money(row.sales)} <span className="text-xs font-semibold text-ink-400">{share}%</span>
                                                </Td>
                                                <Td className="hidden md:table-cell">
                                                    <div className="h-2 rounded-full bg-ink-700">
                                                        <div className="h-2 rounded-full bg-brand-600" style={{ width: `${share}%` }} />
                                                    </div>
                                                </Td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </Table>
                        </Card>
                    )}

                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader title="Sales by day" />
                            <div className="max-h-96 overflow-y-auto">
                                <Table>
                                    <thead>
                                        <tr>
                                            <Th>Date</Th>
                                            <Th className="text-right">Orders</Th>
                                            <Th className="text-right">Sales</Th>
                                            <Th className="w-2/5" aria-label="Share" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {byDay.map((day) => (
                                            <tr key={day.date}>
                                                <Td className="whitespace-nowrap">
                                                    {new Date(`${day.date}T00:00:00`).toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short' })}
                                                </Td>
                                                <Td className="text-right">{day.orders}</Td>
                                                <Td className="text-right font-bold whitespace-nowrap text-flame-400">{money(day.sales)}</Td>
                                                <Td>
                                                    <div className="h-2 rounded-full bg-ink-700">
                                                        <div className="h-2 rounded-full bg-brand-600" style={{ width: `${(day.sales / maxDay) * 100}%` }} />
                                                    </div>
                                                </Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                            </div>
                        </Card>

                        <div className="space-y-6">
                            <BreakdownCard title="By payment method" rows={byPaymentMethod} total={totals.sales} />
                            <BreakdownCard title="By order type" rows={byType} total={totals.sales} />
                        </div>
                    </div>

                    <Card className="mt-6">
                        <CardHeader title="Sales by product" />
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Product</Th>
                                    <Th className="text-right">Quantity</Th>
                                    <Th className="text-right">Revenue</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {byProduct.map((product) => (
                                    <tr key={product.name}>
                                        <Td>
                                            {product.code && <span className="mr-2 text-ink-400">{product.code}</span>}
                                            <span className="font-semibold text-strong">{product.name}</span>
                                        </Td>
                                        <Td className="text-right">{product.quantity}</Td>
                                        <Td className="text-right font-bold text-flame-400">{money(product.revenue)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                        <p className="px-4 py-3 text-xs text-ink-400">Item revenue includes options and combo upgrades, and excludes delivery fees.</p>
                    </Card>
                </>
            )}
        </BackOfficeLayout>
    );
}

function BreakdownCard({ title, rows, total }: { title: string; rows: Breakdown[]; total: number }) {
    return (
        <Card>
            <CardHeader title={title} />
            <ul className="space-y-3 p-4">
                {rows.map((row) => {
                    const share = total > 0 ? Math.round((row.sales / total) * 100) : 0;

                    return (
                        <li key={row.value}>
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="font-semibold text-ink-100">
                                    {row.label} <span className="text-xs font-normal text-ink-400">· {plural(row.orders, 'order')}</span>
                                </span>
                                <span className="font-bold whitespace-nowrap text-flame-400">
                                    {money(row.sales)} <span className="text-xs font-semibold text-ink-400">{share}%</span>
                                </span>
                            </div>
                            <div className="mt-1.5 h-2 rounded-full bg-ink-700">
                                <div className="h-2 rounded-full bg-brand-600" style={{ width: `${share}%` }} />
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}
