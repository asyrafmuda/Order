import { Head, usePage } from '@inertiajs/react';
import { BellRing, ChefHat, Flame, Plug, Timer } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { useLiveReload } from '@/hooks/useLiveReload';
import { useNow } from '@/hooks/useNow';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { cn } from '@/lib/cn';
import { minutesUntil, time } from '@/lib/format';
import type { OrderStatus, OrderType } from '@/types';

interface BoardOrder {
    order_number: string;
    status: OrderStatus;
    type: OrderType;
    confirmed_at: string | null;
    estimated_ready_at: string | null;
}

interface StatusProps {
    board: BoardOrder[];
    stations: { id: number; name: string; queued: number; cooking: number; done: number }[];
    sync: { synced_today: number; failed_today: number; completed_today: number; last_synced_at: string | null };
}

const loadTone = { quiet: 'text-go-400', normal: 'text-go-400', busy: 'text-flame-400', slammed: 'text-brand-400' } as const;

export default function StatusIndex({ board, stations, sync }: StatusProps) {
    const { kitchen, pos } = usePage().props;
    const now = useNow(15000);

    useLiveReload([{ name: 'shop' }], { only: ['board', 'stations', 'sync', 'kitchen'] });

    const preparing = board.filter((order) => order.status !== 'ready');
    const ready = board.filter((order) => order.status === 'ready');

    return (
        <StorefrontLayout>
            <Head title="Kitchen & POS Status" />

            <div className="mb-6">
                <p className="flex items-center gap-1.5 text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">
                    <span className="size-1.5 animate-pulse-soft rounded-full bg-brand-500" aria-hidden /> Live board
                </p>
                <h1 className="font-display text-5xl leading-none text-white uppercase">Kitchen &amp; POS Status</h1>
            </div>

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat icon={<ChefHat className="size-5" />} label="Kitchen load" value={kitchen.label} valueClassName={loadTone[kitchen.level]} hint={`${kitchen.active_orders} order${kitchen.active_orders === 1 ? '' : 's'} in the queue`} />
                <Stat icon={<Timer className="size-5" />} label="Current wait" value={`${kitchen.eta_min}–${kitchen.eta_max} min`} hint="For a new pickup order" />
                <Stat icon={<Flame className="size-5" />} label="On the grill" value={String(kitchen.cooking_items)} hint="Items cooking right now" />
                <Stat icon={<Plug className="size-5" />} label={`POS (${pos.provider})`} value={pos.connected ? 'Connected' : 'Offline'} valueClassName={pos.connected ? 'text-go-400' : 'text-brand-400'} hint={sync.last_synced_at ? `Last ticket synced ${time(sync.last_synced_at)}` : 'No tickets synced yet'} />
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Card>
                    <CardHeader icon={<Flame className="size-4" />} title="Now preparing" action={<Badge tone="red">{preparing.length}</Badge>} />
                    {preparing.length === 0 ? (
                        <EmptyState title="The grill is clear">New orders appear here the moment they are confirmed.</EmptyState>
                    ) : (
                        <ul className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
                            {preparing.map((order) => {
                                const left = minutesUntil(order.estimated_ready_at, now);

                                return (
                                    <li key={order.order_number} className={cn('rounded-xl border p-3 text-center', order.status === 'preparing' ? 'border-brand-600/50 bg-brand-900' : 'border-ink-700 bg-ink-900')}>
                                        <p className="font-display text-4xl leading-none text-white">{order.order_number}</p>
                                        <p className="mt-1 text-[11px] font-bold tracking-wide text-ink-300 uppercase">{order.status === 'preparing' ? 'Cooking' : 'In queue'}</p>
                                        {left !== null && <p className="text-[11px] text-flame-400">{left > 0 ? `~${left} min` : 'almost ready'}</p>}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>

                <Card className="border-go-500/40">
                    <CardHeader icon={<BellRing className="size-4" />} title="Ready for pickup" action={<Badge tone="solid-green">{ready.length}</Badge>} />
                    {ready.length === 0 ? (
                        <EmptyState title="Nothing waiting at the counter">Your queue token shows here when the order is ready.</EmptyState>
                    ) : (
                        <ul className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
                            {ready.map((order) => (
                                <li key={order.order_number} className="rounded-xl border border-go-500/50 bg-go-900 p-3 text-center">
                                    <p className="font-display text-4xl leading-none text-go-400">{order.order_number}</p>
                                    <p className="mt-1 text-[11px] font-bold tracking-wide text-ink-200 uppercase">{order.type === 'delivery' ? 'With rider soon' : 'Collect now'}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <Card className="mt-5">
                <CardHeader icon={<ChefHat className="size-4" />} title="Kitchen Station Monitor" action={<span className="text-xs text-ink-400">{sync.completed_today} orders served today · {sync.synced_today} synced to POS</span>} />
                <ul className="grid gap-px bg-ink-700 sm:grid-cols-2 lg:grid-cols-3">
                    {stations.map((station, index) => {
                        const busy = station.cooking > 0;

                        return (
                            <li key={station.id} className="bg-ink-850 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="flex items-center gap-2 text-sm font-bold text-white">
                                        <span className={cn('size-2 rounded-full', busy ? 'animate-pulse-soft bg-brand-500' : station.queued > 0 ? 'bg-flame-400' : 'bg-go-400')} />
                                        Station {index + 1}: {station.name}
                                    </p>
                                    <Badge tone={busy ? 'solid-red' : station.queued > 0 ? 'flame' : 'green'}>{busy ? 'Cooking' : station.queued > 0 ? 'Queued' : 'Clear'}</Badge>
                                </div>
                                <p className="mt-2 text-xs text-ink-400">
                                    {station.cooking} cooking · {station.queued} waiting · {station.done} plated
                                </p>
                            </li>
                        );
                    })}
                </ul>
            </Card>
        </StorefrontLayout>
    );
}

function Stat({ icon, label, value, hint, valueClassName }: { icon: React.ReactNode; label: string; value: string; hint: string; valueClassName?: string }) {
    return (
        <Card className="p-4">
            <p className="flex items-center gap-2 text-[11px] font-extrabold tracking-wider text-ink-400 uppercase">
                <span className="text-flame-400">{icon}</span>
                {label}
            </p>
            <p className={cn('mt-2 font-display text-4xl leading-none text-white', valueClassName)}>{value}</p>
            <p className="mt-1 text-xs text-ink-400">{hint}</p>
        </Card>
    );
}
