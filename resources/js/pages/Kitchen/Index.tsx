import { Head, router } from '@inertiajs/react';
import { Bike, ChefHat, CircleCheckBig, ShoppingBag } from 'lucide-react';
import { KitchenTicket } from '@/components/staff/KitchenTicket';
import { EmptyState } from '@/components/ui/Card';
import { useLiveReload } from '@/hooks/useLiveReload';
import { useNow } from '@/hooks/useNow';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { minutesSince, plural, time } from '@/lib/format';
import type { Order } from '@/types';

interface StationLoad {
    id: number;
    name: string;
    queued: number;
    cooking: number;
}

interface KitchenProps {
    orders: Order[];
    ready: Order[];
    stations: StationLoad[];
    filters: { station: number | null };
}

export default function KitchenIndex({ orders, ready, stations, filters }: KitchenProps) {
    const now = useNow(15000);

    useLiveReload([{ name: 'staff', private: true }], { only: ['orders', 'ready', 'stations', 'kitchen'] });

    const selectStation = (stationId: number | null) =>
        router.get(route('kitchen.index'), stationId ? { station: stationId } : {}, { preserveScroll: true, preserveState: true, replace: true });

    const totals = stations.reduce((sum, station) => ({ queued: sum.queued + station.queued, cooking: sum.cooking + station.cooking }), { queued: 0, cooking: 0 });

    return (
        <BackOfficeLayout
            wide
            title="Kitchen Display"
            description={`${plural(orders.length, 'ticket')} in the queue · ${plural(totals.cooking, 'portion')} cooking · ${totals.queued} waiting`}
            actions={
                <span className="hidden rounded-lg border border-ink-700 bg-ink-850 px-3 py-1.5 font-display text-2xl leading-none text-flame-400 tabular-nums sm:block" aria-label="Current time">
                    {time(new Date(now).toISOString())}
                </span>
            }
        >
            <Head title="Kitchen Display" />

            <nav className="scrollbar-none -mx-4 mb-5 flex gap-2 overflow-x-auto px-4" aria-label="Kitchen stations">
                <StationTab label="All stations" queued={totals.queued} cooking={totals.cooking} active={filters.station === null} onClick={() => selectStation(null)} />
                {stations.map((station) => (
                    <StationTab
                        key={station.id}
                        label={station.name}
                        queued={station.queued}
                        cooking={station.cooking}
                        active={filters.station === station.id}
                        onClick={() => selectStation(station.id)}
                    />
                ))}
            </nav>

            {orders.length === 0 ? (
                <div className="rounded-xl border border-dashed border-ink-700 bg-ink-900">
                    <EmptyState icon={<ChefHat className="size-12" />} title={filters.station ? 'Nothing for this station' : 'The queue is clear'}>
                        New tickets appear here the moment an order is confirmed. Nice work, crew.
                    </EmptyState>
                </div>
            ) : (
                <div className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,21rem),1fr))] items-start gap-4">
                    {orders.map((order) => (
                        <KitchenTicket key={order.uuid} order={order} now={now} stationId={filters.station} />
                    ))}
                </div>
            )}

            {ready.length > 0 && (
                <section className="mt-8" aria-labelledby="recently-ready">
                    <h2 id="recently-ready" className="mb-3 flex items-center gap-2 text-xs font-extrabold tracking-[0.15em] text-ink-400 uppercase">
                        <CircleCheckBig className="size-4 text-go-400" aria-hidden />
                        Recently ready
                    </h2>
                    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
                        {ready.map((order) => (
                            <li key={order.uuid} className="rounded-lg border border-ink-700 bg-ink-900 px-3 py-2 opacity-70">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-display text-2xl leading-none text-go-400">{order.order_number}</span>
                                    <span className="flex items-center gap-1 text-[11px] font-semibold text-ink-400">
                                        {order.type === 'delivery' ? <Bike className="size-3.5" aria-hidden /> : <ShoppingBag className="size-3.5" aria-hidden />}
                                        {order.type_label}
                                    </span>
                                </div>
                                <p className="mt-1 truncate text-xs text-ink-400">
                                    {plural(order.items_count ?? 0, 'item')} · ready {minutesSince(order.ready_at, now)} min ago
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </BackOfficeLayout>
    );
}

interface StationTabProps {
    label: string;
    queued: number;
    cooking: number;
    active: boolean;
    onClick: () => void;
}

function StationTab({ label, queued, cooking, active, onClick }: StationTabProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'flex h-12 shrink-0 items-center gap-2.5 rounded-xl border px-4 text-sm font-bold whitespace-nowrap transition-colors',
                active ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 bg-ink-850 text-ink-200 hover:border-ink-500',
            )}
        >
            {label}
            <span className="flex items-center gap-1 text-[11px] font-extrabold">
                <span className={cn('rounded-md px-1.5 py-0.5', cooking > 0 ? 'bg-ink-950/50 text-flame-300' : 'bg-ink-950/30 text-ink-400')} aria-label={`${cooking} cooking`}>
                    🔥 {cooking}
                </span>
                <span className={cn('rounded-md px-1.5 py-0.5', queued > 0 ? 'bg-ink-950/50 text-white' : 'bg-ink-950/30 text-ink-400')} aria-label={`${queued} waiting`}>
                    ⏳ {queued}
                </span>
            </span>
        </button>
    );
}
