import { router, useForm } from '@inertiajs/react';
import { Banknote, Bike, Ban, ExternalLink, FileDown, HandPlatter, MapPin, Phone, Send, StickyNote } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { DeliveryStatusBadge, KitchenStatusBadge, OrderStatusBadge, PaymentStatusBadge, SyncStatusBadge } from '@/components/ui/Badge';
import { Button, buttonClass } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { dateTime, money, time } from '@/lib/format';
import type { Order } from '@/types';

export interface CounterRider {
    id: number;
    name: string;
    phone: string | null;
    vehicle_type: string;
    plate_number: string | null;
    is_available: boolean;
    open_deliveries: number;
}

interface HandoverPanelProps {
    order: Order;
    riders: CounterRider[];
    onClose: () => void;
}

const keep = { preserveScroll: true, preserveState: true } as const;

/** Everything the cashier can do with one order: take cash, hand over, assign and dispatch a rider, cancel. */
export function HandoverPanel({ order, riders, onClose }: HandoverPanelProps) {
    const [busy, setBusy] = useState<string | null>(null);
    const [cancelling, setCancelling] = useState(false);
    const [riderId, setRiderId] = useState<string>(order.delivery?.rider ? String(order.delivery.rider.id) : '');
    const cancelForm = useForm({ reason: '' });

    const items = order.items ?? [];
    const delivery = order.delivery ?? null;
    const awaitingCash = order.payment_method === 'cash' && !order.is_paid && !order.is_final;
    const riderOnTheWay = delivery !== null && (delivery.status === 'picked_up' || delivery.status === 'delivered');

    const post = (key: string, name: string) => {
        setBusy(key);
        router.post(route(name, order.uuid), {}, { ...keep, onFinish: () => setBusy(null) });
    };

    const assignRider = (event: FormEvent) => {
        event.preventDefault();
        setBusy('rider');
        router.put(route('counter.orders.rider', order.uuid), { rider_id: riderId === '' ? null : Number(riderId) }, { ...keep, onFinish: () => setBusy(null) });
    };

    const cancel = (event: FormEvent) => {
        event.preventDefault();
        cancelForm.post(route('counter.orders.cancel', order.uuid), {
            ...keep,
            onSuccess: () => {
                cancelForm.reset();
                setCancelling(false);
            },
        });
    };

    // Why the hand-over button is disabled, if it is.
    const handoverBlocker = order.is_final
        ? `This order is ${order.status_label.toLowerCase()}.`
        : order.status !== 'ready'
          ? 'The kitchen has not marked this order ready yet.'
          : !order.is_paid
            ? 'Collect the cash before handing the order over.'
            : null;

    const dispatchBlocker = !delivery
        ? null
        : riderOnTheWay
          ? null
          : order.status !== 'ready'
            ? 'The order can leave once the kitchen marks it ready.'
            : !delivery.rider
              ? 'Assign a rider first.'
              : null;

    return (
        <Modal
            open
            onClose={onClose}
            size="lg"
            title={
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-display text-3xl leading-none">Order {order.order_number}</span>
                    <OrderStatusBadge status={order.status} label={order.status_label} />
                    <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                    <SyncStatusBadge status={order.pos.status} />
                </span>
            }
            footer={
                <div className="flex w-full flex-wrap items-center gap-2">
                    <a href={route('orders.show', order.uuid)} target="_blank" rel="noreferrer" className={buttonClass('ghost', 'sm')}>
                        <ExternalLink className="size-4" aria-hidden /> Public ticket
                    </a>
                    <a href={route('orders.invoice', order.uuid)} className={buttonClass('ghost', 'sm')}>
                        <FileDown className="size-4" aria-hidden /> Invoice
                    </a>
                    {!order.is_final && !cancelling && (
                        <Button variant="danger" size="sm" className="ml-auto" icon={<Ban className="size-4" aria-hidden />} onClick={() => setCancelling(true)}>
                            Cancel order
                        </Button>
                    )}
                </div>
            }
        >
            <div className="space-y-5">
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Fact label="Customer">
                        {order.customer_name}
                        {order.customer_phone ? (
                            <a href={`tel:+${order.customer_phone}`} className="flex items-center gap-1 text-xs font-normal text-flame-400 hover:underline">
                                <Phone className="size-3" aria-hidden />+{order.customer_phone}
                            </a>
                        ) : (
                            <span className="block text-xs font-normal text-ink-400">No phone given</span>
                        )}
                    </Fact>
                    <Fact label={order.type === 'dine_in' ? 'Serve to' : 'Pickup code'}>
                        <span className={order.type === 'dine_in' ? 'font-display text-2xl leading-none text-flame-400 uppercase' : 'font-mono text-lg tracking-[0.2em] text-flame-400'}>
                            {order.type === 'dine_in' ? (order.table_label ?? 'Table') : order.pickup_code}
                        </span>
                    </Fact>
                    <Fact label={order.type_label}>
                        Placed {time(order.created_at)}
                        <span className="block text-xs font-normal text-ink-400">
                            {order.ready_at ? `Ready ${time(order.ready_at)}` : order.estimated_ready_at ? `Due ${time(order.estimated_ready_at)}` : 'Awaiting confirmation'}
                        </span>
                    </Fact>
                    <Fact label="Payment">
                        {order.payment_method_label}
                        {order.payment?.transaction_id && <span className="block truncate text-xs font-normal text-ink-400">{order.payment.transaction_id}</span>}
                    </Fact>
                </dl>

                {order.notes && (
                    <p className="flex items-start gap-2 rounded-lg bg-flame-900 px-3 py-2 text-sm font-semibold text-flame-300">
                        <StickyNote className="mt-0.5 size-4 shrink-0" aria-hidden />
                        {order.notes}
                    </p>
                )}

                {order.status === 'cancelled' && order.cancel_reason && (
                    <p className="rounded-lg bg-brand-900 px-3 py-2 text-sm font-semibold text-brand-400">Cancelled: {order.cancel_reason}</p>
                )}

                {delivery && (
                    <section className="space-y-3 rounded-lg border border-ink-700 bg-ink-900 p-3" aria-label="Delivery">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="flex items-center gap-2 text-sm font-bold text-white">
                                <Bike className="size-4 text-flame-400" aria-hidden /> Delivery
                                {delivery.zone && <span className="font-normal text-ink-400">· {delivery.zone}</span>}
                            </h3>
                            <DeliveryStatusBadge status={delivery.status} label={delivery.status_label} />
                        </div>
                        <p className="flex items-start gap-2 text-sm text-ink-200">
                            <MapPin className="mt-0.5 size-4 shrink-0 text-brand-400" aria-hidden />
                            <span>
                                <span className="font-semibold">{delivery.recipient_name}</span> · +{delivery.phone}
                                <span className="block text-ink-300">{delivery.address}</span>
                                {delivery.notes && <span className="block text-xs text-flame-300">Note: {delivery.notes}</span>}
                            </span>
                        </p>

                        {!order.is_final && !riderOnTheWay && (
                            <form onSubmit={assignRider} className="flex items-end gap-2">
                                <Select label="Rider" wrapperClassName="flex-1" value={riderId} onChange={(event) => setRiderId(event.target.value)}>
                                    <option value="">No rider (unassign)</option>
                                    {riders.map((rider) => (
                                        <option key={rider.id} value={rider.id}>
                                            {rider.name} — {rider.is_available ? 'available' : 'off roster'} · {rider.open_deliveries} open
                                        </option>
                                    ))}
                                </Select>
                                <Button type="submit" variant="secondary" loading={busy === 'rider'} disabled={riderId === String(delivery.rider?.id ?? '')}>
                                    Assign
                                </Button>
                            </form>
                        )}

                        {delivery.rider && riderOnTheWay && (
                            <p className="text-sm text-ink-300">
                                Rider: <span className="font-semibold text-ink-100">{delivery.rider.name}</span>
                                {delivery.rider.plate_number && ` · ${delivery.rider.plate_number}`}
                                {delivery.picked_up_at && ` · left ${time(delivery.picked_up_at)}`}
                            </p>
                        )}

                        {!order.is_final && !riderOnTheWay && (
                            <div>
                                <Button
                                    className="w-full"
                                    size="lg"
                                    icon={<Send className="size-4" aria-hidden />}
                                    loading={busy === 'dispatch'}
                                    disabled={dispatchBlocker !== null || busy !== null}
                                    onClick={() => post('dispatch', 'counter.orders.dispatch')}
                                >
                                    Dispatch rider
                                </Button>
                                {dispatchBlocker && <p className="mt-1.5 text-center text-xs text-ink-400">{dispatchBlocker}</p>}
                            </div>
                        )}
                    </section>
                )}

                {!order.is_final && (awaitingCash || !delivery) && (
                    <section className="space-y-2" aria-label="Hand-over">
                        {awaitingCash && (
                            <Button
                                variant="flame"
                                size="lg"
                                className="w-full"
                                icon={<Banknote className="size-5" aria-hidden />}
                                loading={busy === 'pay'}
                                disabled={busy !== null}
                                onClick={() => post('pay', 'counter.orders.pay')}
                            >
                                Collect cash {money(order.total)}
                            </Button>
                        )}
                        {!delivery && (
                            <>
                                <Button
                                    variant="success"
                                    size="lg"
                                    className="w-full"
                                    icon={<HandPlatter className="size-5" aria-hidden />}
                                    loading={busy === 'complete'}
                                    disabled={handoverBlocker !== null || busy !== null}
                                    onClick={() => post('complete', 'counter.orders.complete')}
                                >
                                    Hand over &amp; complete
                                </Button>
                                {handoverBlocker && <p className="text-center text-xs text-ink-400">{handoverBlocker}</p>}
                            </>
                        )}
                    </section>
                )}

                {cancelling && (
                    <form onSubmit={cancel} className="space-y-3 rounded-lg border border-brand-600/50 bg-brand-900/40 p-3">
                        <Input
                            label="Reason for cancelling"
                            hint={order.is_paid ? 'The payment will be refunded and the customer is notified.' : 'The customer is notified with this reason.'}
                            placeholder="e.g. Item out of stock"
                            autoFocus
                            required
                            maxLength={255}
                            value={cancelForm.data.reason}
                            onChange={(event) => cancelForm.setData('reason', event.target.value)}
                            error={cancelForm.errors.reason}
                        />
                        <div className="flex justify-end gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setCancelling(false)}>
                                Keep order
                            </Button>
                            <Button type="submit" size="sm" loading={cancelForm.processing}>
                                Cancel order {order.order_number}
                            </Button>
                        </div>
                    </form>
                )}

                <section aria-label="Items">
                    <ul className="divide-y divide-ink-700 rounded-lg border border-ink-700">
                        {items.map((item) => {
                            const details = [
                                item.variant_name,
                                ...item.modifiers.map((modifier) => modifier.name),
                                item.combo_name && `${item.combo_name}${item.combo_choice ? `: ${item.combo_choice}` : ''}`,
                            ].filter(Boolean);

                            return (
                                <li key={item.id} className="flex items-start gap-3 px-3 py-2.5">
                                    <span className="w-7 shrink-0 text-sm font-extrabold text-flame-400">{item.quantity}×</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold text-white">
                                            {item.code && <span className="text-ink-400">{item.code}. </span>}
                                            {item.name}
                                        </p>
                                        {details.length > 0 && <p className="text-xs text-ink-400">{details.join(' • ')}</p>}
                                        {item.notes && <p className="text-xs font-medium text-go-400">Notes: {item.notes}</p>}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        <span className="text-sm font-semibold text-ink-100">{money(item.line_total)}</span>
                                        <KitchenStatusBadge status={item.kitchen_status} label={item.kitchen_status_label} />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    <dl className="mt-3 space-y-1 text-sm">
                        <div className="flex justify-between text-ink-300">
                            <dt>Subtotal</dt>
                            <dd>{money(order.subtotal)}</dd>
                        </div>
                        {order.delivery_fee > 0 && (
                            <div className="flex justify-between text-ink-300">
                                <dt>Delivery fee</dt>
                                <dd>{money(order.delivery_fee)}</dd>
                            </div>
                        )}
                        <div className="flex items-end justify-between pt-1">
                            <dt className="font-bold text-white">Total</dt>
                            <dd className="font-display text-3xl leading-none text-flame-400">{money(order.total)}</dd>
                        </div>
                    </dl>
                </section>

                {order.histories && order.histories.length > 0 && (
                    <section aria-label="History">
                        <h3 className="mb-2 text-[11px] font-extrabold tracking-wider text-ink-400 uppercase">History</h3>
                        <ol className="space-y-1 text-xs text-ink-300">
                            {order.histories.map((entry) => (
                                <li key={entry.id} className="flex flex-wrap gap-x-2">
                                    <span className="text-ink-500">{dateTime(entry.at)}</span>
                                    <span className="font-semibold text-ink-100">{entry.status_label}</span>
                                    {entry.note && <span>— {entry.note}</span>}
                                    {entry.by && <span className="text-ink-500">by {entry.by}</span>}
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
            </div>
        </Modal>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="rounded-lg border border-ink-700 bg-ink-900 px-3 py-2">
            <dt className="text-[10px] font-extrabold tracking-wider text-ink-400 uppercase">{label}</dt>
            <dd className="mt-0.5 text-sm font-semibold text-ink-100">{children}</dd>
        </div>
    );
}
