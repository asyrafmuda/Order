import { Bike, ChevronRight } from 'lucide-react';
import { Badge, DeliveryStatusBadge, OrderStatusBadge, PaymentStatusBadge } from '@/components/ui/Badge';
import { cn } from '@/lib/cn';
import { minutesSince, money, time } from '@/lib/format';
import type { Order } from '@/types';

interface CounterOrderCardProps {
    order: Order;
    selected: boolean;
    onSelect: () => void;
}

/** Compact order summary on the counter board; opens the hand-over panel. */
export function CounterOrderCard({ order, selected, onSelect }: CounterOrderCardProps) {
    const items = order.items ?? [];
    const shown = items.slice(0, 3);
    const awaitingCash = order.payment_method === 'cash' && !order.is_paid;

    // How long a ready order has been sitting at the counter; the board refreshes often enough to keep this current.
    const waiting = order.status === 'ready' ? (minutesSince(order.ready_at) ?? 0) : 0;

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-label={`Open order ${order.order_number}`}
            className={cn(
                'group w-full rounded-xl border bg-ink-900 p-3 text-left transition-colors hover:border-ink-400',
                selected ? 'border-flame-400' : 'border-ink-700',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-display text-3xl leading-none text-white">{order.order_number}</p>
                    <p className="mt-1 truncate text-xs font-semibold text-ink-200">{order.customer_name}</p>
                    <p className="text-[11px] text-ink-400">
                        {order.type === 'dine_in' ? 'Dine-in' : order.source === 'pos' ? 'Counter order' : null}
                        {order.customer_phone && `${order.source === 'pos' ? ' · ' : ''}+${order.customer_phone}`}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <OrderStatusBadge status={order.status} label={order.status_label} />
                    {awaitingCash ? <Badge tone="solid-flame">Collect cash</Badge> : <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />}
                </div>
            </div>

            <ul className="mt-2.5 space-y-0.5 text-xs text-ink-300">
                {shown.map((item) => (
                    <li key={item.id} className="truncate">
                        <span className="font-bold text-ink-100">{item.quantity}×</span> {item.name}
                        {item.variant_name && <span className="text-ink-400"> · {item.variant_name}</span>}
                    </li>
                ))}
                {items.length > shown.length && <li className="text-ink-500">+{items.length - shown.length} more</li>}
            </ul>

            {order.delivery && (
                <p className="mt-2.5 flex flex-wrap items-center gap-1.5 text-[11px] text-ink-300">
                    <Bike className="size-3.5 text-flame-400" aria-hidden />
                    {order.delivery.rider ? <span className="font-semibold text-ink-100">{order.delivery.rider.name}</span> : <Badge tone="flame">No rider yet</Badge>}
                    <DeliveryStatusBadge status={order.delivery.status} label={order.delivery.status_label} />
                </p>
            )}

            <div className="mt-2.5 flex items-center justify-between border-t border-ink-700 pt-2">
                <span className={cn('text-[11px]', waiting >= 30 ? 'font-bold text-brand-400' : waiting >= 15 ? 'font-bold text-flame-400' : 'text-ink-400')}>
                    {order.ready_at
                        ? `Ready ${time(order.ready_at)}${order.status === 'ready' && waiting >= 5 ? ` · waiting ${waiting} min` : ''}`
                        : order.estimated_ready_at
                          ? `Due ${time(order.estimated_ready_at)}`
                          : `In ${time(order.created_at)}`}
                </span>
                <span className="flex items-center gap-1 text-sm font-extrabold text-flame-400">
                    {money(order.total)}
                    <ChevronRight className="size-4 text-ink-500 transition-transform group-hover:translate-x-0.5" aria-hidden />
                </span>
            </div>
        </button>
    );
}
