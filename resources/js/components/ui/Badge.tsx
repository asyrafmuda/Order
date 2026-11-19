import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import type { DeliveryStatus, KitchenItemStatus, OrderStatus, PaymentStatus, SyncStatus } from '@/types';

export type Tone = 'neutral' | 'red' | 'flame' | 'green' | 'blue' | 'solid-red' | 'solid-flame' | 'solid-green';

const tones: Record<Tone, string> = {
    neutral: 'bg-ink-700 text-ink-200',
    red: 'bg-brand-900 text-brand-400 ring-1 ring-inset ring-brand-600/40',
    flame: 'bg-flame-900 text-flame-400 ring-1 ring-inset ring-flame-500/30',
    green: 'bg-go-900 text-go-400 ring-1 ring-inset ring-go-500/30',
    blue: 'bg-sky-950 text-sky-300 ring-1 ring-inset ring-sky-500/30',
    'solid-red': 'bg-brand-600 text-white',
    'solid-flame': 'bg-flame-400 text-ink-950',
    'solid-green': 'bg-go-500 text-ink-950',
};

interface BadgeProps {
    tone?: Tone;
    className?: string;
    children: ReactNode;
}

export function Badge({ tone = 'neutral', className, children }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold tracking-wide whitespace-nowrap uppercase',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

const orderTones: Record<OrderStatus, Tone> = {
    pending_payment: 'flame',
    confirmed: 'blue',
    preparing: 'solid-red',
    ready: 'solid-green',
    out_for_delivery: 'blue',
    completed: 'green',
    cancelled: 'neutral',
};

export function OrderStatusBadge({ status, label }: { status: OrderStatus; label: string }) {
    return <Badge tone={orderTones[status]}>{label}</Badge>;
}

const paymentTones: Record<PaymentStatus, Tone> = {
    pending: 'flame',
    paid: 'green',
    failed: 'red',
    refunded: 'neutral',
};

export function PaymentStatusBadge({ status, label }: { status: PaymentStatus; label: string }) {
    return <Badge tone={paymentTones[status]}>{label}</Badge>;
}

const kitchenTones: Record<KitchenItemStatus, Tone> = {
    queued: 'neutral',
    cooking: 'solid-red',
    done: 'green',
};

export function KitchenStatusBadge({ status, label }: { status: KitchenItemStatus; label: string }) {
    return <Badge tone={kitchenTones[status]}>{label}</Badge>;
}

const deliveryTones: Record<DeliveryStatus, Tone> = {
    pending: 'flame',
    assigned: 'blue',
    picked_up: 'solid-red',
    delivered: 'green',
    failed: 'neutral',
};

export function DeliveryStatusBadge({ status, label }: { status: DeliveryStatus; label: string }) {
    return <Badge tone={deliveryTones[status]}>{label}</Badge>;
}

const syncTones: Record<SyncStatus, Tone> = {
    pending: 'flame',
    synced: 'green',
    failed: 'red',
};

export function SyncStatusBadge({ status }: { status: SyncStatus }) {
    return <Badge tone={syncTones[status]}>{status === 'synced' ? 'POS synced' : status === 'failed' ? 'POS failed' : 'POS pending'}</Badge>;
}
