import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, Bike, ChefHat, CircleCheckBig, ClipboardList, HandPlatter, ScanLine, Search, TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { CounterOrderCard } from '@/components/staff/CounterOrderCard';
import { HandoverPanel, type CounterRider } from '@/components/staff/HandoverPanel';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Input } from '@/components/ui/Form';
import { useLiveReload } from '@/hooks/useLiveReload';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { money, plural } from '@/lib/format';
import type { Order } from '@/types';

interface CounterProps {
    kitchenOrders: Order[];
    readyOrders: Order[];
    deliveryOrders: Order[];
    riders: CounterRider[];
    stats: {
        active: number;
        completed_today: number;
        cash_to_collect: number;
        cash_orders: number;
        awaiting_rider: number;
    };
    selected: Order | null;
    lookupFailed: boolean;
}

const partial = { preserveScroll: true, preserveState: true, only: ['selected', 'lookupFailed'] };

export default function CounterIndex({ kitchenOrders, readyOrders, deliveryOrders, riders, stats, selected, lookupFailed }: CounterProps) {
    const verify = useForm({ code: '' });

    useLiveReload([{ name: 'staff', private: true }], {
        only: ['kitchenOrders', 'readyOrders', 'deliveryOrders', 'riders', 'stats', 'selected', 'kitchen'],
    });

    const open = (order: Order) => router.get(route('counter.index'), { order: order.uuid }, partial);
    const close = () => router.get(route('counter.index'), {}, partial);

    const submitVerify = (event: FormEvent) => {
        event.preventDefault();
        verify.post(route('counter.verify'), { preserveScroll: true, onSuccess: () => verify.reset() });
    };

    const column = (orders: Order[]) =>
        orders.map((order) => <CounterOrderCard key={order.uuid} order={order} selected={selected?.uuid === order.uuid} onSelect={() => open(order)} />);

    return (
        <BackOfficeLayout title="Counter" description={`${plural(stats.active, 'active order')} · ${readyOrders.length} ready to hand over`}>
            <Head title="Counter" />

            <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                <Stat icon={<ClipboardList className="size-5" />} label="Active orders" value={String(stats.active)} />
                <Stat
                    icon={<Banknote className="size-5" />}
                    label="Cash to collect"
                    value={money(stats.cash_to_collect)}
                    hint={stats.cash_orders > 0 ? plural(stats.cash_orders, 'unpaid order') : 'All settled'}
                    highlight={stats.cash_orders > 0}
                />
                <Stat icon={<Bike className="size-5" />} label="Awaiting a rider" value={String(stats.awaiting_rider)} highlight={stats.awaiting_rider > 0} />
                <Stat icon={<CircleCheckBig className="size-5" />} label="Completed today" value={String(stats.completed_today)} />
            </div>

            <Card className="mt-4 p-4">
                <form onSubmit={submitVerify} className="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <Input
                        label="Verify pickup"
                        wrapperClassName="flex-1"
                        prefix={<ScanLine className="size-4" aria-hidden />}
                        placeholder="Pickup code or today's order number, e.g. K7M2QX or A012"
                        autoComplete="off"
                        autoCapitalize="characters"
                        className="font-mono tracking-wider uppercase placeholder:font-sans placeholder:tracking-normal placeholder:normal-case"
                        value={verify.data.code}
                        onChange={(event) => verify.setData('code', event.target.value)}
                        error={verify.errors.code}
                        hint="Scanning the QR on the customer's ticket opens the order here directly."
                    />
                    <Button type="submit" className="sm:mt-[1.375rem]" icon={<Search className="size-4" aria-hidden />} loading={verify.processing} disabled={verify.data.code.trim() === ''}>
                        Find order
                    </Button>
                </form>
                {lookupFailed && (
                    <p className="mt-3 flex items-center gap-2 rounded-lg bg-brand-900 px-3 py-2 text-sm font-semibold text-brand-400" role="alert">
                        <TriangleAlert className="size-4 shrink-0" aria-hidden />
                        No order matches that code. Ask the customer for their order number instead.
                    </p>
                )}
            </Card>

            <div className="mt-4 grid items-start gap-4 md:grid-cols-3">
                <Column title="In the kitchen" icon={<ChefHat className="size-4" />} count={kitchenOrders.length} empty="No pickup orders are cooking right now.">
                    {column(kitchenOrders)}
                </Column>
                {/* On a phone the orders waiting for a customer come first. */}
                <Column className="order-first md:order-none" title="Ready to hand over" icon={<HandPlatter className="size-4" />} count={readyOrders.length} tone="solid-green" empty="Nothing is waiting at the counter.">
                    {column(readyOrders)}
                </Column>
                <Column title="Deliveries" icon={<Bike className="size-4" />} count={deliveryOrders.length} empty="No open delivery orders.">
                    {column(deliveryOrders)}
                </Column>
            </div>

            {selected && <HandoverPanel key={selected.uuid} order={selected} riders={riders} onClose={close} />}
        </BackOfficeLayout>
    );
}

interface StatProps {
    icon: ReactNode;
    label: string;
    value: string;
    hint?: string;
    highlight?: boolean;
}

function Stat({ icon, label, value, hint, highlight }: StatProps) {
    return (
        <Card className="flex items-center gap-3 px-3 py-2.5 sm:px-4 sm:py-3">
            <span className={`hidden sm:block ${highlight ? 'text-flame-400' : 'text-ink-400'}`} aria-hidden>
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-[11px] font-bold tracking-wider text-ink-400 uppercase">{label}</p>
                <p className={`font-display text-2xl leading-none whitespace-nowrap sm:text-3xl ${highlight ? 'text-flame-400' : 'text-white'}`}>{value}</p>
                {hint && <p className="mt-0.5 text-[11px] text-ink-400">{hint}</p>}
            </div>
        </Card>
    );
}

interface ColumnProps {
    title: string;
    icon: ReactNode;
    count: number;
    tone?: 'neutral' | 'solid-green';
    empty: string;
    className?: string;
    children: ReactNode;
}

function Column({ title, icon, count, tone = 'neutral', empty, className, children }: ColumnProps) {
    return (
        <Card className={className}>
            <CardHeader title={title} icon={icon} action={<Badge tone={count > 0 ? tone : 'neutral'}>{count}</Badge>} />
            {count === 0 ? <EmptyState title={empty} /> : <div className="space-y-2.5 p-3">{children}</div>}
        </Card>
    );
}
