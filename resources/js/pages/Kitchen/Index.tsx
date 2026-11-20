import { Head, router } from '@inertiajs/react';
import { Bell, BellOff, BellRing, Bike, ChefHat, CircleCheckBig, PackageX, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { KitchenTicket } from '@/components/staff/KitchenTicket';
import { StockPanel, type StockCategory } from '@/components/staff/StockPanel';
import { EmptyState } from '@/components/ui/Card';
import { useLiveReload } from '@/hooks/useLiveReload';
import { useNow } from '@/hooks/useNow';
import { useOrderChime } from '@/hooks/useOrderChime';
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
    menu: StockCategory[];
}

export default function KitchenIndex({ orders, ready, stations, filters, menu }: KitchenProps) {
    const now = useNow(15000);
    const [stockOpen, setStockOpen] = useState(false);
    const { fresh, sound, toggle: toggleSound } = useOrderChime(orders.map((order) => order.uuid));
    const soldOut = menu.flatMap((category) => category.products).filter((product) => product.status === 'sold_out').length;

    useLiveReload([{ name: 'staff', private: true, perOutlet: true }], { only: ['orders', 'ready', 'stations', 'kitchen', 'menu'] });

    const selectStation = (stationId: number | null) =>
        router.get(route('kitchen.index'), stationId ? { station: stationId } : {}, { preserveScroll: true, preserveState: true, replace: true });

    const totals = stations.reduce((sum, station) => ({ queued: sum.queued + station.queued, cooking: sum.cooking + station.cooking }), { queued: 0, cooking: 0 });

    return (
        <BackOfficeLayout
            wide
            title="Kitchen Display"
            description={`${plural(orders.length, 'ticket')} in the queue · ${plural(totals.cooking, 'portion')} cooking · ${totals.queued} waiting`}
            actions={
                <>
                    <button
                        type="button"
                        onClick={() => setStockOpen(true)}
                        className={cn(
                            'flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-bold',
                            soldOut > 0 ? 'border-brand-600 bg-brand-900 text-brand-400' : 'border-ink-700 bg-ink-850 text-ink-200 hover:border-ink-500',
                        )}
                    >
                        <PackageX className="size-4" aria-hidden />
                        <span className="hidden sm:inline">Stock</span>
                        {soldOut > 0 && <span className="rounded bg-brand-600 px-1.5 text-xs text-white">{soldOut}</span>}
                    </button>
                    <button
                        type="button"
                        onClick={toggleSound}
                        aria-pressed={sound !== 'off'}
                        title={sound === 'blocked' ? 'Tap anywhere once so the browser allows sound' : undefined}
                        className={cn(
                            // Fixed width: the label changes on the first tap, and nothing next to it may move under the finger.
                            'flex h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-bold sm:w-48',
                            sound === 'on' && 'border-go-500/40 bg-go-900 text-go-400',
                            sound === 'blocked' && 'animate-pulse-soft border-flame-500/50 bg-flame-900 text-flame-300',
                            sound === 'off' && 'border-ink-700 bg-ink-850 text-ink-400',
                        )}
                    >
                        {sound === 'on' ? <BellRing className="size-4" aria-hidden /> : sound === 'blocked' ? <Bell className="size-4" aria-hidden /> : <BellOff className="size-4" aria-hidden />}
                        <span className="hidden sm:inline">{sound === 'on' ? 'Sound on' : sound === 'blocked' ? 'Tap to enable sound' : 'Sound off'}</span>
                    </button>
                    <span className="hidden rounded-lg border border-ink-700 bg-ink-850 px-3 py-1.5 font-display text-2xl leading-none text-flame-400 tabular-nums sm:block" aria-label="Current time">
                        {time(new Date(now).toISOString())}
                    </span>
                </>
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
                        <div key={order.uuid} className={cn('rounded-2xl transition-shadow duration-500', fresh.has(order.uuid) && 'animate-pulse-soft ring-4 ring-flame-400')}>
                            <KitchenTicket order={order} now={now} stationId={filters.station} />
                        </div>
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

            {stockOpen && <StockPanel menu={menu} onClose={() => setStockOpen(false)} />}
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
