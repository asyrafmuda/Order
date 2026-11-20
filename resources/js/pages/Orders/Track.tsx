import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronRight, ReceiptText, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { OrderStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Input } from '@/components/ui/Form';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { dateTime, money } from '@/lib/format';
import type { Order } from '@/types';

export default function OrderTrack({ recent }: { recent: Order[] }) {
    const form = useForm({ order_number: '', phone: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('orders.lookup'));
    };

    return (
        <StorefrontLayout>
            <Head title="Order Tracking" />

            <div className="mb-6">
                <p className="flex items-center gap-1.5 text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">
                    <span className="size-1.5 rounded-full bg-brand-500" aria-hidden /> Live from the kitchen
                </p>
                <h1 className="font-display text-5xl leading-none text-white uppercase">Order Tracking &amp; Digital Ticket</h1>
            </div>

            <div className="grid gap-5 lg:grid-cols-[24rem_minmax(0,1fr)]">
                <Card className="self-start">
                    <CardHeader icon={<Search className="size-4" />} title="Find my order" />
                    <form onSubmit={submit} className="space-y-4 p-4">
                        <Input
                            label="Queue token"
                            placeholder="A001"
                            required
                            value={form.data.order_number}
                            onChange={(event) => form.setData('order_number', event.target.value.toUpperCase())}
                            error={form.errors.order_number}
                            className="font-mono tracking-widest uppercase"
                        />
                        <Input
                            label="Mobile number used for the order"
                            type="tel"
                            placeholder="012-345 6789"
                            required
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            error={form.errors.phone}
                        />
                        <Button type="submit" className="w-full" loading={form.processing}>
                            Open my ticket
                        </Button>
                    </form>
                </Card>

                <Card>
                    <CardHeader icon={<ReceiptText className="size-4" />} title="Your recent orders" />
                    {recent.length === 0 ? (
                        <EmptyState icon={<ReceiptText className="size-8" />} title="No recent orders on this device">
                            Orders you place here show up automatically. You can also look one up with its queue token.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-ink-800">
                            {recent.map((order) => (
                                <li key={order.uuid}>
                                    <Link href={route('orders.show', order.uuid)} className="flex items-center gap-4 px-4 py-3.5 hover:bg-ink-800">
                                        <span className="font-display text-3xl leading-none text-brand-500">#{order.order_number}</span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold text-white">{(order.items ?? []).map((item) => `${item.quantity}× ${item.name}`).join(', ')}</span>
                                            <span className="text-xs text-ink-400">
                                                {dateTime(order.created_at)} · {order.type_label}
                                            </span>
                                        </span>
                                        <span className="hidden text-sm font-bold text-flame-400 sm:block">{money(order.total)}</span>
                                        <OrderStatusBadge status={order.status} label={order.status_label} />
                                        <ChevronRight className="size-4 text-ink-500" aria-hidden />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </StorefrontLayout>
    );
}
