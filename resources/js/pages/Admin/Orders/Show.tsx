import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, Bike, ExternalLink, FileDown, History, MessageSquareText, Plug, ReceiptText, RefreshCw, UserRound } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Badge, DeliveryStatusBadge, KitchenStatusBadge, OrderStatusBadge, PaymentStatusBadge, SyncStatusBadge } from '@/components/ui/Badge';
import { Button, buttonClass } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Textarea } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { dateTime, money, time } from '@/lib/format';
import type { Order } from '@/types';

interface PosLog {
    id: number;
    provider: string;
    action: string;
    status: string;
    error: string | null;
    at: string | null;
}

interface NotificationLog {
    id: number;
    channel: string;
    recipient: string;
    template: string;
    message: string;
    status: string;
    error: string | null;
    at: string | null;
}

interface OrderShowProps {
    order: Order;
    posLogs: PosLog[];
    notifications: NotificationLog[];
}

export default function OrderShow({ order, posLogs, notifications }: OrderShowProps) {
    const [cancelling, setCancelling] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const canResync = order.pos.status !== 'synced' && order.status !== 'pending_payment' && order.status !== 'cancelled';

    const resync = () =>
        router.post(route('admin.orders.resync', order.uuid), {}, { preserveScroll: true, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) });

    return (
        <BackOfficeLayout
            title={`Order #${order.order_number}`}
            description={`Placed ${dateTime(order.created_at)} · ${order.type_label}`}
            actions={
                <>
                    <a href={route('orders.invoice', order.uuid)} className={buttonClass('outline', 'sm')}>
                        <FileDown className="size-4" aria-hidden /> Invoice
                    </a>
                    {!order.is_final && (
                        <Button variant="danger" size="sm" onClick={() => setCancelling(true)} icon={<Ban className="size-4" aria-hidden />}>
                            Cancel order
                        </Button>
                    )}
                </>
            }
        >
            <Head title={`Order #${order.order_number}`} />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Link href={route('admin.orders.index')} className="mr-2 flex items-center gap-1 text-sm font-semibold text-ink-300 hover:text-strong">
                    <ArrowLeft className="size-4" aria-hidden /> All orders
                </Link>
                <OrderStatusBadge status={order.status} label={order.status_label} />
                <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                <SyncStatusBadge status={order.pos.status} />
                <Badge tone={order.type === 'delivery' ? 'blue' : 'neutral'}>{order.type_label}</Badge>
                <a href={route('orders.show', order.uuid)} target="_blank" rel="noreferrer" className="ml-auto flex items-center gap-1 text-xs font-semibold text-flame-400 hover:underline">
                    Customer ticket <ExternalLink className="size-3.5" aria-hidden />
                </a>
            </div>

            {order.status === 'cancelled' && order.cancel_reason && (
                <p className="mb-4 rounded-lg border border-danger-600/40 bg-danger-900 px-4 py-3 text-sm text-ink-100">
                    <strong className="font-bold text-danger-400">Cancelled:</strong> {order.cancel_reason}
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader title="Items" icon={<ReceiptText className="size-4" />} />
                        <ul className="divide-y divide-ink-800">
                            {(order.items ?? []).map((item) => {
                                const details = [
                                    item.variant_name,
                                    ...item.modifiers.map((modifier) => (modifier.price > 0 ? `${modifier.name} (+${money(modifier.price)})` : modifier.name)),
                                    item.combo_name && `+ ${item.combo_name}${item.combo_choice ? `: ${item.combo_choice}` : ''} (+${money(item.combo_price)})`,
                                ].filter(Boolean);

                                return (
                                    <li key={item.id} className="flex items-start gap-3 px-4 py-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-ink-700 text-sm font-extrabold text-strong">{item.quantity}×</span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold text-strong">
                                                {item.code && <span className="text-ink-400">{item.code}. </span>}
                                                {item.name}
                                            </p>
                                            {details.length > 0 && <p className="text-xs text-ink-400">{details.join(' • ')}</p>}
                                            {item.notes && <p className="text-xs font-medium text-go-400">Notes: {item.notes}</p>}
                                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                                <KitchenStatusBadge status={item.kitchen_status} label={item.kitchen_status_label} />
                                                {item.station && <span className="text-[11px] text-ink-500">{item.station.name}</span>}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-bold text-flame-400">{money(item.line_total)}</p>
                                            <p className="text-[11px] text-ink-500">{money(item.unit_price)} each</p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                        <dl className="space-y-1.5 border-t border-ink-700 px-4 py-3 text-sm">
                            <Row label="Subtotal" value={money(order.subtotal)} />
                            {order.delivery_fee > 0 && <Row label="Delivery fee" value={money(order.delivery_fee)} />}
                            {order.tax_amount > 0 && <Row label={`SST ${order.tax_rate}%${order.tax_inclusive ? ' (included)' : ''}`} value={money(order.tax_amount)} />}
                            <div className="flex items-end justify-between pt-2">
                                <dt className="font-bold text-strong">Total</dt>
                                <dd className="font-display text-3xl leading-none text-flame-400">{money(order.total)}</dd>
                            </div>
                        </dl>
                        {order.notes && (
                            <p className="border-t border-ink-700 px-4 py-3 text-sm text-ink-300">
                                <strong className="font-semibold text-ink-100">Order notes:</strong> {order.notes}
                            </p>
                        )}
                    </Card>

                    <Card>
                        <CardHeader title="Status history" icon={<History className="size-4" />} />
                        <ol className="divide-y divide-ink-800">
                            {(order.histories ?? []).map((history) => (
                                <li key={history.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5">
                                    <OrderStatusBadge status={history.status} label={history.status_label} />
                                    <span className="min-w-0 flex-1 text-sm text-ink-300">{history.note ?? '—'}</span>
                                    <span className="text-xs text-ink-400">
                                        {history.by ? `${history.by} · ` : ''}
                                        {dateTime(history.at)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </Card>

                    <Card>
                        <CardHeader title="Customer messages" icon={<MessageSquareText className="size-4" />} />
                        {notifications.length === 0 ? (
                            <EmptyState title="No messages sent">Order updates sent to the customer are logged here.</EmptyState>
                        ) : (
                            <ul className="divide-y divide-ink-800">
                                {notifications.map((log) => (
                                    <li key={log.id} className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge tone={log.status === 'sent' ? 'green' : 'red'}>{log.status}</Badge>
                                            <span className="text-xs font-semibold text-ink-200">{log.template}</span>
                                            <span className="text-xs text-ink-400">
                                                via {log.channel} to +{log.recipient}
                                            </span>
                                            <span className="ml-auto text-xs text-ink-400">{dateTime(log.at)}</span>
                                        </div>
                                        <p className="mt-1.5 text-xs leading-relaxed whitespace-pre-line text-ink-300">{log.message}</p>
                                        {log.error && <p className="mt-1 text-xs text-danger-400">{log.error}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Customer" icon={<UserRound className="size-4" />} />
                        <dl className="space-y-2 px-4 py-3 text-sm">
                            <Row label="Name" value={order.customer_name} />
                            <Row
                                label="Phone"
                                value={
                                    order.customer_phone ? (
                                        <a href={`tel:+${order.customer_phone}`} className="text-flame-400 hover:underline">
                                            +{order.customer_phone}
                                        </a>
                                    ) : (
                                        'Walk-in, none given'
                                    )
                                }
                            />
                            <Row label="Taken" value={order.source_label} />
                            {order.customer_email && <Row label="Email" value={order.customer_email} />}
                            <Row label="Pickup code" value={<span className="font-mono font-bold tracking-widest text-strong">{order.pickup_code}</span>} />
                        </dl>
                    </Card>

                    <Card>
                        <CardHeader title="Payment" icon={<ReceiptText className="size-4" />} />
                        <dl className="space-y-2 px-4 py-3 text-sm">
                            <Row label="Method" value={order.payment_method_label} />
                            <Row label="Status" value={<PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />} />
                            {order.payment && (
                                <>
                                    <Row label="Gateway" value={order.payment.gateway} />
                                    <Row label="Reference" value={<span className="font-mono text-xs">{order.payment.reference}</span>} />
                                    {order.payment.transaction_id && <Row label="Transaction" value={<span className="font-mono text-xs">{order.payment.transaction_id}</span>} />}
                                    {order.payment.paid_at && <Row label="Paid at" value={dateTime(order.payment.paid_at)} />}
                                </>
                            )}
                        </dl>
                    </Card>

                    {order.delivery && (
                        <Card>
                            <CardHeader title="Delivery" icon={<Bike className="size-4" />} action={<DeliveryStatusBadge status={order.delivery.status} label={order.delivery.status_label} />} />
                            <dl className="space-y-2 px-4 py-3 text-sm">
                                <Row label="Recipient" value={order.delivery.recipient_name} />
                                <Row label="Phone" value={`+${order.delivery.phone}`} />
                                <Row label="Zone" value={order.delivery.zone ?? '—'} />
                                <Row label="Rider" value={order.delivery.rider ? `${order.delivery.rider.name}${order.delivery.rider.plate_number ? ` (${order.delivery.rider.plate_number})` : ''}` : 'Not assigned'} />
                                {order.delivery.picked_up_at && <Row label="Picked up" value={time(order.delivery.picked_up_at)} />}
                                {order.delivery.delivered_at && <Row label="Delivered" value={time(order.delivery.delivered_at)} />}
                            </dl>
                            <p className="border-t border-ink-700 px-4 py-3 text-sm text-ink-200">{order.delivery.address}</p>
                            {order.delivery.notes && <p className="border-t border-ink-700 px-4 py-3 text-xs text-ink-400">Note: {order.delivery.notes}</p>}
                        </Card>
                    )}

                    <Card>
                        <CardHeader
                            title="POS sync"
                            icon={<Plug className="size-4" />}
                            action={
                                canResync && (
                                    <Button size="xs" variant="secondary" onClick={resync} loading={syncing} icon={<RefreshCw className="size-3.5" aria-hidden />}>
                                        Sync now
                                    </Button>
                                )
                            }
                        />
                        <dl className="space-y-2 px-4 py-3 text-sm">
                            <Row label="Status" value={<SyncStatusBadge status={order.pos.status} />} />
                            <Row label="Receipt" value={order.pos.receipt_number ?? '—'} />
                            {order.pos.synced_at && <Row label="Synced at" value={dateTime(order.pos.synced_at)} />}
                        </dl>
                        {posLogs.length > 0 && (
                            <ul className="divide-y divide-ink-800 border-t border-ink-700">
                                {posLogs.map((log) => (
                                    <li key={log.id} className="px-4 py-2.5 text-xs">
                                        <div className="flex items-center gap-2">
                                            <Badge tone={log.status === 'synced' ? 'green' : log.status === 'failed' ? 'red' : 'neutral'}>{log.status}</Badge>
                                            <span className="text-ink-300">
                                                {log.provider} · {log.action}
                                            </span>
                                            <span className="ml-auto text-ink-500">{dateTime(log.at)}</span>
                                        </div>
                                        {log.error && <p className="mt-1 text-danger-400">{log.error}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>

            {cancelling && <CancelDialog order={order} onClose={() => setCancelling(false)} />}
        </BackOfficeLayout>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <dt className="shrink-0 text-ink-400">{label}</dt>
            <dd className="min-w-0 text-right font-medium break-words text-ink-100">{value}</dd>
        </div>
    );
}

function CancelDialog({ order, onClose }: { order: Order; onClose: () => void }) {
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('admin.orders.cancel', order.uuid), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal open onClose={onClose} title={`Cancel order #${order.order_number}?`} size="sm">
            <form onSubmit={submit} className="space-y-4">
                <p className="text-sm text-ink-300">
                    The kitchen stops working on it and the customer is told.
                    {order.is_paid && <strong className="text-flame-400"> The payment of {money(order.total)} will be refunded.</strong>}
                </p>
                <Textarea
                    label="Reason (shown to the customer)"
                    required
                    autoFocus
                    maxLength={255}
                    placeholder="e.g. Item out of stock"
                    value={form.data.reason}
                    onChange={(event) => form.setData('reason', event.target.value)}
                    error={form.errors.reason}
                />
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        Keep order
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        Cancel order
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
