import { router } from '@inertiajs/react';
import { Bike, Check, CheckCheck, Flame, Play, ShoppingBag, StickyNote, Timer, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { Badge, KitchenStatusBadge, OrderStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import { minutesSince, time } from '@/lib/format';
import type { KitchenItemStatus, Order, OrderItem } from '@/types';

const keep = { preserveScroll: true, preserveState: true } as const;

/** Minutes after which a ticket is running late / badly late. */
const WARN_AFTER = 10;
const LATE_AFTER = 15;

interface KitchenTicketProps {
    order: Order;
    now: number;
    /** When a station tab is active, items of other stations are de-emphasised. */
    stationId: number | null;
}

/** One order on the kitchen display, with per-item and whole-order actions. */
export function KitchenTicket({ order, now, stationId }: KitchenTicketProps) {
    const [busy, setBusy] = useState<string | null>(null);
    const items = order.items ?? [];
    const elapsed = minutesSince(order.confirmed_at ?? order.created_at, now) ?? 0;
    const urgency = elapsed >= LATE_AFTER ? 'late' : elapsed >= WARN_AFTER ? 'warn' : 'ok';
    const hasQueued = items.some((item) => item.kitchen_status === 'queued');
    const done = items.filter((item) => item.kitchen_status === 'done').length;

    const run = (key: string, visit: (onFinish: () => void) => void) => {
        setBusy(key);
        visit(() => setBusy(null));
    };

    const setItemStatus = (item: OrderItem, status: KitchenItemStatus) =>
        run(`item-${item.id}`, (onFinish) => router.patch(route('kitchen.items.update', item.id), { status }, { ...keep, onFinish }));

    return (
        <article
            className={cn('flex flex-col overflow-hidden rounded-xl border-2 bg-ink-850', {
                'border-ink-700': urgency === 'ok',
                'border-flame-500/70': urgency === 'warn',
                'border-brand-500': urgency === 'late',
            })}
            aria-label={`Order ${order.order_number}`}
        >
            <header className="space-y-2 border-b border-ink-700 px-4 pt-3 pb-2.5">
                <div className="flex items-start justify-between gap-3">
                    <p className="font-display text-5xl leading-none text-white">{order.order_number}</p>
                    <span
                        className={cn('flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-extrabold tabular-nums', {
                            'bg-go-900 text-go-400': urgency === 'ok',
                            'bg-flame-900 text-flame-400': urgency === 'warn',
                            'animate-pulse-soft bg-brand-600 text-white': urgency === 'late',
                        })}
                        aria-label={`${elapsed} minutes in the kitchen`}
                    >
                        <Timer className="size-4" aria-hidden />
                        {elapsed} min
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Badge tone={order.type === 'delivery' ? 'blue' : 'neutral'}>
                        {order.type === 'delivery' ? <Bike className="size-3" aria-hidden /> : <ShoppingBag className="size-3" aria-hidden />}
                        {order.type === 'delivery' ? 'Delivery' : 'Pickup'}
                    </Badge>
                    <OrderStatusBadge status={order.status} label={order.status_label} />
                    <span className="min-w-0 truncate text-xs font-semibold text-ink-300">
                        {order.customer_name.split(' ')[0]} · in at {time(order.confirmed_at ?? order.created_at)}
                    </span>
                </div>
            </header>

            {order.notes && (
                <p className="flex items-start gap-2 border-b border-flame-500/30 bg-flame-900 px-4 py-2 text-sm font-semibold text-flame-300">
                    <StickyNote className="mt-0.5 size-4 shrink-0" aria-hidden />
                    {order.notes}
                </p>
            )}

            <ul className="flex-1 divide-y divide-ink-700">
                {items.map((item) => (
                    <KitchenTicketItem
                        key={item.id}
                        item={item}
                        dimmed={stationId !== null && item.station?.id !== stationId}
                        busy={busy === `item-${item.id}`}
                        onChange={(status) => setItemStatus(item, status)}
                    />
                ))}
            </ul>

            <footer className="space-y-2 border-t border-ink-700 p-3">
                <p className="text-center text-[11px] font-semibold text-ink-400">
                    {done} of {items.length} item{items.length === 1 ? '' : 's'} done
                </p>
                <Button
                    variant="success"
                    size="lg"
                    className="w-full"
                    icon={<CheckCheck className="size-5" aria-hidden />}
                    loading={busy === 'ready'}
                    disabled={busy !== null}
                    onClick={() => run('ready', (onFinish) => router.post(route('kitchen.orders.ready', order.uuid), {}, { ...keep, onFinish }))}
                >
                    Mark Order Ready!
                </Button>
                {hasQueued && (
                    <Button
                        variant="secondary"
                        className="w-full"
                        icon={<Flame className="size-4" aria-hidden />}
                        loading={busy === 'start'}
                        disabled={busy !== null}
                        onClick={() => run('start', (onFinish) => router.post(route('kitchen.orders.start', order.uuid), {}, { ...keep, onFinish }))}
                    >
                        Start all
                    </Button>
                )}
            </footer>
        </article>
    );
}

interface KitchenTicketItemProps {
    item: OrderItem;
    dimmed: boolean;
    busy: boolean;
    onChange: (status: KitchenItemStatus) => void;
}

function KitchenTicketItem({ item, dimmed, busy, onChange }: KitchenTicketItemProps) {
    const isDone = item.kitchen_status === 'done';

    return (
        <li className={cn('flex gap-3 px-4 py-3 transition-opacity', dimmed && 'opacity-40', item.kitchen_status === 'cooking' && 'bg-brand-900/30')}>
            <span className={cn('w-9 shrink-0 font-display text-3xl leading-none', isDone ? 'text-ink-500' : 'text-flame-400')}>{item.quantity}×</span>

            <div className="min-w-0 flex-1">
                <p className={cn('text-[15px] leading-snug font-bold', isDone ? 'text-ink-400 line-through' : 'text-white')}>
                    {item.code && <span className="text-ink-400">{item.code}. </span>}
                    {item.name}
                </p>
                {item.variant_name && <p className="text-sm font-semibold text-ink-200">{item.variant_name}</p>}
                {item.modifiers.length > 0 && (
                    <ul className="mt-0.5 text-sm text-ink-300">
                        {item.modifiers.map((modifier, index) => (
                            <li key={index}>+ {modifier.name}</li>
                        ))}
                    </ul>
                )}
                {item.combo_name && (
                    <p className="mt-0.5 text-sm font-semibold text-sky-300">
                        {item.combo_name}
                        {item.combo_choice && `: ${item.combo_choice}`}
                    </p>
                )}
                {item.notes && <p className="mt-1 rounded-md bg-flame-900 px-2 py-1 text-sm font-semibold text-flame-300">“{item.notes}”</p>}
                <p className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <KitchenStatusBadge status={item.kitchen_status} label={item.kitchen_status_label} />
                    {item.station && <span className="text-[11px] font-semibold text-ink-400">{item.station.name}</span>}
                </p>
            </div>

            <div className="shrink-0 self-center">
                {item.kitchen_status === 'queued' && (
                    <Button variant="flame" size="sm" icon={<Play className="size-4" aria-hidden />} loading={busy} onClick={() => onChange('cooking')} aria-label={`Start ${item.name}`}>
                        Start
                    </Button>
                )}
                {item.kitchen_status === 'cooking' && (
                    <Button variant="success" size="sm" icon={<Check className="size-4" aria-hidden />} loading={busy} onClick={() => onChange('done')} aria-label={`Finish ${item.name}`}>
                        Done
                    </Button>
                )}
                {isDone && (
                    <Button variant="ghost" size="xs" icon={<Undo2 className="size-3.5" aria-hidden />} loading={busy} onClick={() => onChange('cooking')} aria-label={`Put ${item.name} back on the heat`}>
                        Undo
                    </Button>
                )}
            </div>
        </li>
    );
}
