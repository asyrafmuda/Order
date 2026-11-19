import { router } from '@inertiajs/react';
import { CircleCheckBig, MapPin, MessageCircle, Navigation, Phone, StickyNote } from 'lucide-react';
import { useState } from 'react';
import { Badge, DeliveryStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { money, time } from '@/lib/format';
import type { DeliveryStatus, OrderStatus } from '@/types';

export interface RiderDelivery {
    id: number;
    status: DeliveryStatus;
    status_label: string;
    recipient_name: string;
    phone: string;
    address: string;
    notes: string | null;
    fee: number;
    zone: string | null;
    eta_minutes: number | null;
    assigned_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    order: {
        uuid: string;
        order_number: string;
        status: OrderStatus;
        status_label: string;
        customer_name: string;
        notes: string | null;
        total: number;
        is_paid: boolean;
        estimated_ready_at: string | null;
        ready_at: string | null;
        items: {
            id: number;
            quantity: number;
            name: string;
            variant_name: string | null;
            combo_name: string | null;
            combo_choice: string | null;
            modifiers: string[];
        }[];
    };
}

const keep = { preserveScroll: true, preserveState: true } as const;

const contactLink =
    'flex h-10 items-center justify-center gap-1.5 rounded-lg bg-ink-700 px-2 text-[13px] font-semibold whitespace-nowrap text-ink-100 transition-colors hover:bg-ink-600';

/** One delivery on the rider's run sheet, with navigation, contact and the next action. */
export function RiderDeliveryCard({ delivery }: { delivery: RiderDelivery }) {
    const [busy, setBusy] = useState(false);
    const { order } = delivery;
    const onTheWay = delivery.status === 'picked_up';
    const readyToCollect = order.status === 'ready';
    const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(delivery.address)}`;

    const post = (name: string) => {
        setBusy(true);
        router.post(route(name, delivery.id), {}, { ...keep, onFinish: () => setBusy(false) });
    };

    return (
        <Card className={onTheWay ? 'border-brand-500' : readyToCollect ? 'border-go-500/60' : undefined}>
            <div className="flex items-start justify-between gap-3 border-b border-ink-700 px-4 py-3">
                <div>
                    <p className="font-display text-4xl leading-none text-white">{order.order_number}</p>
                    <p className="mt-1 text-xs text-ink-400">
                        {delivery.zone ?? 'Delivery'}
                        {delivery.assigned_at && ` · assigned ${time(delivery.assigned_at)}`}
                    </p>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    <DeliveryStatusBadge status={delivery.status} label={delivery.status_label} />
                    {!onTheWay &&
                        (readyToCollect ? (
                            <Badge tone="solid-green">Ready to collect</Badge>
                        ) : (
                            <Badge tone="flame">Kitchen: {order.status_label}</Badge>
                        ))}
                </div>
            </div>

            <div className="space-y-3 px-4 py-3">
                <div className="flex items-start gap-2.5">
                    <MapPin className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-sm font-bold text-white">{delivery.recipient_name}</p>
                        <p className="text-sm leading-snug text-ink-200">{delivery.address}</p>
                    </div>
                </div>

                {(delivery.notes || order.notes) && (
                    <p className="flex items-start gap-2 rounded-lg bg-flame-900 px-3 py-2 text-sm font-semibold text-flame-300">
                        <StickyNote className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <span>
                            {delivery.notes}
                            {delivery.notes && order.notes && <br />}
                            {order.notes && <span className="font-normal">Order note: {order.notes}</span>}
                        </span>
                    </p>
                )}

                <div className="grid grid-cols-3 gap-2">
                    <a href={mapsUrl} target="_blank" rel="noreferrer" className={contactLink}>
                        <Navigation className="size-4" aria-hidden /> Maps
                    </a>
                    <a href={`tel:+${delivery.phone}`} className={contactLink} aria-label={`Call ${delivery.recipient_name}`}>
                        <Phone className="size-4" aria-hidden /> Call
                    </a>
                    <a
                        href={`https://wa.me/${delivery.phone}?text=${encodeURIComponent(`Hi ${delivery.recipient_name}, I'm your rider for order ${order.order_number}.`)}`}
                        target="_blank"
                        rel="noreferrer"
                        className={contactLink}
                        aria-label={`WhatsApp ${delivery.recipient_name}`}
                    >
                        <MessageCircle className="size-4" aria-hidden /> WhatsApp
                    </a>
                </div>

                <ul className="space-y-1 rounded-lg border border-ink-700 bg-ink-900 px-3 py-2.5 text-sm text-ink-200">
                    {order.items.map((item) => {
                        const details = [item.variant_name, ...item.modifiers, item.combo_name && `${item.combo_name}${item.combo_choice ? `: ${item.combo_choice}` : ''}`].filter(Boolean);

                        return (
                            <li key={item.id}>
                                <span className="font-extrabold text-flame-400">{item.quantity}×</span> <span className="font-semibold">{item.name}</span>
                                {details.length > 0 && <span className="block pl-6 text-xs text-ink-400">{details.join(' • ')}</span>}
                            </li>
                        );
                    })}
                </ul>

                <div className="flex items-center justify-between text-sm">
                    <span className="text-ink-300">
                        Order total <span className="font-bold text-ink-100">{money(order.total)}</span>
                    </span>
                    {order.is_paid ? <Badge tone="green">Paid online</Badge> : <Badge tone="solid-flame">Collect {money(order.total)}</Badge>}
                </div>
            </div>

            <div className="border-t border-ink-700 p-3">
                {onTheWay ? (
                    <ConfirmButton
                        variant="success"
                        size="lg"
                        className="w-full"
                        icon={<CircleCheckBig className="size-5" aria-hidden />}
                        title={`Order ${order.order_number} delivered?`}
                        message={`Confirm that ${delivery.recipient_name} has received the order. This completes the delivery.`}
                        confirmLabel="Yes, delivered"
                        disabled={busy}
                        onConfirm={() => post('rider.deliveries.deliver')}
                    >
                        Delivered
                    </ConfirmButton>
                ) : (
                    <>
                        <Button size="lg" className="w-full" icon={<Navigation className="size-5" aria-hidden />} loading={busy} disabled={!readyToCollect} onClick={() => post('rider.deliveries.pickup')}>
                            Picked up — start delivery
                        </Button>
                        {!readyToCollect && (
                            <p className="mt-1.5 text-center text-xs text-ink-400">
                                The kitchen is still on it{order.estimated_ready_at ? ` — due around ${time(order.estimated_ready_at)}` : ''}.
                            </p>
                        )}
                    </>
                )}
            </div>
        </Card>
    );
}
