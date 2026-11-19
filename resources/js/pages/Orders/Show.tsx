import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bike, ChefHat, Clock, Download, FileText, MapPin, MessageCircle, Navigation, RefreshCw, ScanLine, ShoppingBag, Store, TrendingUp, User } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { OrderTimeline } from '@/components/storefront/OrderTimeline';
import { ProductImage } from '@/components/ProductImage';
import { Badge, DeliveryStatusBadge, KitchenStatusBadge } from '@/components/ui/Badge';
import { Button, buttonClass } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { useLiveReload } from '@/hooks/useLiveReload';
import { useNow } from '@/hooks/useNow';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { cn } from '@/lib/cn';
import { minutesUntil, money, time } from '@/lib/format';
import type { KitchenItemStatus, Order, OrderItem } from '@/types';

export default function OrderShow({ order }: { order: Order }) {
    const { shop, pos } = usePage().props;
    const now = useNow(15000);

    useLiveReload([{ name: `orders.${order.uuid}` }], { only: ['order', 'kitchen', 'flash'], enabled: !order.is_final });

    const delivery = order.type === 'delivery';
    const cancelled = order.status === 'cancelled';
    const items = order.items ?? [];

    // Delivery orders are due when the rider arrives, not when the kitchen finishes.
    const target = order.estimated_ready_at
        ? new Date(new Date(order.estimated_ready_at).getTime() + (delivery ? (order.delivery?.eta_minutes ?? 0) * 60000 : 0)).toISOString()
        : null;
    const minutesLeft = minutesUntil(target, now);

    const headline = cancelled
        ? 'Order cancelled'
        : order.status === 'pending_payment'
          ? 'Waiting for payment'
          : order.is_paid
            ? `Order confirmed & paid via ${order.payment_method_label}`
            : 'Order confirmed · pay at the counter';

    return (
        <StorefrontLayout
            subheader={
                <div className="no-print border-b border-ink-700 bg-ink-900">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 text-xs">
                        <p className="flex items-center gap-2 font-bold text-white">
                            <span className={cn('size-2 rounded-full', cancelled ? 'bg-ink-500' : order.status === 'pending_payment' ? 'animate-pulse-soft bg-flame-400' : 'bg-go-400')} />
                            {headline}
                        </p>
                        {order.payment?.transaction_id && <p className="text-ink-400">Transaction ID: #{order.payment.transaction_id}</p>}
                        <p className="ml-auto flex items-center gap-1.5 font-semibold text-ink-300">
                            {order.pos.status === 'synced' ? (
                                <span className="rounded-md bg-go-900 px-2 py-1 font-bold text-go-400 ring-1 ring-go-500/30 ring-inset">POS synced (#{order.pos.receipt_number})</span>
                            ) : (
                                !cancelled && order.status !== 'pending_payment' && <span className="rounded-md bg-flame-900 px-2 py-1 font-bold text-flame-400">POS sync {order.pos.status}</span>
                            )}
                            <span className="hidden sm:inline">POS: {pos.provider}</span>
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Order ${order.order_number}`} />

            <div className="space-y-5">
                {/* Ticket hero */}
                <Card className="overflow-hidden bg-[radial-gradient(circle_at_0%_0%,rgb(238_43_50/0.22),transparent_45%)]">
                    <div className="grid gap-6 p-5 md:grid-cols-[minmax(0,1fr)_auto] md:p-6">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge tone={cancelled ? 'neutral' : 'solid-red'}>{cancelled ? 'Cancelled' : order.is_final ? 'Order closed' : 'Live kitchen order'}</Badge>
                                <Badge tone="neutral">{order.status_label}</Badge>
                                {order.estimated_ready_at && !order.is_final && (
                                    <span className="flex items-center gap-1 text-xs text-ink-300">
                                        <Clock className="size-3.5 text-flame-400" aria-hidden /> Estimated prep based on the current kitchen queue
                                    </span>
                                )}
                            </div>

                            <div className="mt-5 flex flex-wrap items-end gap-x-10 gap-y-4">
                                <div>
                                    <p className="text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">Queue token</p>
                                    <p className="font-display text-7xl leading-none text-white uppercase">
                                        Order <span className="text-brand-500">#{order.order_number}</span>
                                    </p>
                                </div>
                                {!cancelled && (
                                    <div>
                                        <p className="text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">
                                            {order.status === 'completed' ? 'Completed at' : order.status === 'ready' && !delivery ? 'Ready since' : delivery ? 'Target arrival' : 'Target ready time'}
                                        </p>
                                        <p className="font-display text-5xl leading-none text-flame-400">
                                            {order.status === 'completed' ? time(order.completed_at) : order.status === 'ready' && !delivery ? time(order.ready_at) : target ? time(target) : '—'}
                                            {!order.is_final && minutesLeft !== null && !(order.status === 'ready' && !delivery) && (
                                                <span className="ml-2 font-sans text-sm font-semibold text-go-400">{minutesLeft > 0 ? `in ~${minutesLeft} min` : 'any moment now'}</span>
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="mt-5 flex flex-wrap items-center gap-2 text-xs">
                                <span className={cn('flex items-center gap-1.5 rounded-lg px-3 py-2 font-bold', delivery ? 'bg-ink-800 text-ink-300' : 'bg-brand-600 text-white')}>
                                    <ShoppingBag className="size-4" aria-hidden /> Self-Pickup ({shop.counter})
                                </span>
                                <span className={cn('flex items-center gap-1.5 rounded-lg px-3 py-2 font-bold', delivery ? 'bg-brand-600 text-white' : 'bg-ink-800 text-ink-300')}>
                                    <Bike className="size-4" aria-hidden /> Delivery dispatch
                                </span>
                                <span className="flex items-center gap-1.5 text-ink-300">
                                    <Store className="size-4 text-flame-400" aria-hidden /> {shop.name} · {shop.address}
                                </span>
                            </div>

                            {order.status === 'pending_payment' && (
                                <div className="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-flame-500/40 bg-flame-900/50 p-4">
                                    <p className="min-w-0 flex-1 text-sm text-flame-300">
                                        {order.payment_status === 'failed' ? 'The last payment attempt did not go through.' : 'This order is held until payment is received.'} The kitchen starts as soon as you pay.
                                    </p>
                                    {order.can.pay && (
                                        <Button onClick={() => router.post(route('orders.pay', order.uuid))}>Pay {money(order.total)} now</Button>
                                    )}
                                    {order.can.cancel && (
                                        <ConfirmButton
                                            title={`Cancel order ${order.order_number}?`}
                                            message="The order will be cancelled and nothing will be charged."
                                            confirmLabel="Cancel order"
                                            variant="outline"
                                            onConfirm={() => router.post(route('orders.cancel', order.uuid))}
                                        >
                                            Cancel order
                                        </ConfirmButton>
                                    )}
                                </div>
                            )}

                            {cancelled && (
                                <p className="mt-5 rounded-xl border border-ink-600 bg-ink-900 p-4 text-sm text-ink-300">
                                    {order.cancel_reason ?? 'This order was cancelled.'}
                                    {order.payment_status === 'refunded' && ' Your payment is being refunded to the original method.'}
                                </p>
                            )}
                        </div>

                        {!cancelled && order.status !== 'pending_payment' && (
                            <div className="flex items-center gap-4 rounded-xl border border-ink-700 bg-ink-900 p-4 md:flex-col md:justify-center md:text-center">
                                <div>
                                    <Badge tone="flame" className="mb-2">
                                        <ScanLine className="size-3" aria-hidden /> Verification scan
                                    </Badge>
                                    <div className="rounded-lg bg-white p-2">
                                        <QRCodeSVG value={route('counter.index', { code: order.pickup_code })} size={112} aria-label={`Pickup QR code for ${order.pickup_code}`} />
                                    </div>
                                </div>
                                <div>
                                    <p className="font-mono text-sm font-bold tracking-widest text-flame-400">PASS: {order.pickup_code}</p>
                                    <p className="text-[11px] text-ink-400">{delivery ? 'Show to the rider on arrival' : `Present at ${shop.counter}`}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="border-t border-ink-700 p-5 md:px-6">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-bold text-white">
                                <TrendingUp className="size-4 text-flame-400" aria-hidden /> Live Order Lifecycle
                            </h2>
                            {!order.is_final && (
                                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-go-400">
                                    <RefreshCw className="size-3" aria-hidden /> Updates automatically
                                </span>
                            )}
                        </div>
                        <OrderTimeline steps={order.timeline} />
                    </div>
                </Card>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    {/* Manifest */}
                    <Card>
                        <CardHeader icon={<FileText className="size-4" />} title="Itemized Order Manifest" action={<Badge>{items.length} line item{items.length === 1 ? '' : 's'}</Badge>} />
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-ink-700 px-4 py-2.5 text-xs">
                            <p className="flex items-center gap-1.5 font-semibold text-ink-100">
                                <User className="size-3.5 text-flame-400" aria-hidden /> {order.customer_name} {order.customer_phone && <span className="font-normal text-ink-400">(+{order.customer_phone})</span>}
                            </p>
                            <p className="font-bold tracking-wider text-flame-400 uppercase">{delivery ? 'Delivery' : `Station: ${shop.counter}`}</p>
                        </div>

                        <ul className="divide-y divide-ink-800">
                            {items.map((item) => (
                                <ManifestLine key={item.id} item={item} showKitchen={!order.is_final && order.status !== 'pending_payment'} />
                            ))}
                        </ul>

                        {order.notes && (
                            <p className="border-t border-ink-700 px-4 py-3 text-xs text-ink-300">
                                <strong className="text-ink-100">Order notes:</strong> {order.notes}
                            </p>
                        )}

                        <dl className="space-y-1.5 border-t border-ink-700 px-4 py-3 text-xs">
                            <Row label={`Subtotal (${order.items_count ?? items.length} items)`} value={money(order.subtotal)} />
                            {delivery && <Row label="Delivery fee" value={money(order.delivery_fee)} />}
                            {order.tax_rate > 0 && <Row label={`Service tax (SST ${order.tax_rate}%)`} value={order.tax_inclusive ? `Included (${money(order.tax_amount)})` : money(order.tax_amount)} />}
                            <div className="flex items-end justify-between pt-2">
                                <dt>
                                    <span className="block text-base font-bold text-white">{order.is_paid ? 'Total Paid' : 'Total'}</span>
                                    <span className={cn('flex items-center gap-1 text-[11px] font-semibold', order.is_paid ? 'text-go-400' : 'text-flame-400')}>
                                        <span className={cn('size-1.5 rounded-full', order.is_paid ? 'bg-go-400' : 'bg-flame-400')} />
                                        {order.is_paid ? `Payment settled via ${order.payment_method_label}` : order.payment_status === 'refunded' ? 'Refunded' : `${order.payment_method_label} · ${order.payment_status_label}`}
                                    </span>
                                </dt>
                                <dd className="font-display text-4xl leading-none whitespace-nowrap text-flame-400">{money(order.total)}</dd>
                            </div>
                        </dl>

                        <div className="no-print grid gap-2 border-t border-ink-700 p-4 sm:grid-cols-3">
                            <a href={route('orders.invoice', order.uuid)} className={buttonClass('secondary', 'md')}>
                                <Download className="size-4" aria-hidden /> Download Invoice
                            </a>
                            <a
                                href={`https://wa.me/${shop.whatsapp}?text=${encodeURIComponent(`Hi ${shop.name}, I'm asking about order ${order.order_number}.`)}`}
                                target="_blank"
                                rel="noreferrer"
                                className={buttonClass('secondary', 'md')}
                            >
                                <MessageCircle className="size-4 text-go-400" aria-hidden /> WhatsApp Us
                            </a>
                            <a href={shop.maps_url} target="_blank" rel="noreferrer" className={buttonClass('secondary', 'md')}>
                                <Navigation className="size-4 text-brand-400" aria-hidden /> Directions
                            </a>
                        </div>
                    </Card>

                    <div className="space-y-5">
                        <StationMonitor items={items} idle={order.status === 'pending_payment' || cancelled} />

                        {delivery && order.delivery ? (
                            <Card>
                                <CardHeader icon={<Bike className="size-4" />} title="Delivery" action={<DeliveryStatusBadge status={order.delivery.status} label={order.delivery.status_label} />} />
                                <div className="space-y-3 p-4 text-sm">
                                    <p className="flex items-start gap-2 text-ink-200">
                                        <MapPin className="mt-0.5 size-4 shrink-0 text-brand-400" aria-hidden />
                                        <span>
                                            <strong className="block text-white">{order.delivery.recipient_name}</strong>
                                            {order.delivery.address}
                                            {order.delivery.zone && <span className="block text-xs text-ink-400">{order.delivery.zone}</span>}
                                        </span>
                                    </p>
                                    {order.delivery.notes && <p className="text-xs text-ink-400">Rider note: {order.delivery.notes}</p>}
                                    {order.delivery.rider ? (
                                        <div className="rounded-lg bg-ink-900 p-3 text-xs">
                                            <p className="font-bold text-white">Rider: {order.delivery.rider.name}</p>
                                            <p className="text-ink-400">
                                                {order.delivery.rider.vehicle_type}
                                                {order.delivery.rider.plate_number && ` · ${order.delivery.rider.plate_number}`}
                                            </p>
                                            {order.delivery.rider.phone && (
                                                <a href={`tel:+${order.delivery.rider.phone}`} className="mt-1 inline-block font-semibold text-flame-400 hover:underline">
                                                    Call rider
                                                </a>
                                            )}
                                        </div>
                                    ) : (
                                        !order.is_final && <p className="text-xs text-ink-400">A rider is assigned once your order is nearly ready.</p>
                                    )}
                                </div>
                            </Card>
                        ) : (
                            <Card>
                                <CardHeader icon={<Store className="size-4" />} title="Pick-Up Counter Desk" />
                                <div className="space-y-2 p-4 text-sm text-ink-300">
                                    <p className="font-semibold text-white">{shop.address}</p>
                                    <p className="text-xs">
                                        Head to <strong className="text-ink-100">{shop.counter}</strong> and show the QR code or pass <strong className="font-mono text-flame-400">{order.pickup_code}</strong> when your number is called.
                                        {!order.is_paid && !order.is_final && ' Payment is taken at the counter.'}
                                    </p>
                                </div>
                            </Card>
                        )}

                        <Link href={route('menu.index')} className={buttonClass('outline', 'md', 'no-print w-full')}>
                            Order something else
                        </Link>
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between text-ink-300">
            <dt>{label}</dt>
            <dd className="font-semibold text-ink-100">{value}</dd>
        </div>
    );
}

function ManifestLine({ item, showKitchen }: { item: OrderItem; showKitchen: boolean }) {
    const details = [item.variant_name, ...item.modifiers.map((modifier) => modifier.name)].filter(Boolean);

    return (
        <li className="flex gap-3 px-4 py-3.5">
            <div className="size-14 shrink-0 overflow-hidden rounded-lg text-base">
                <ProductImage src={item.image_url} alt="" icon={item.icon} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-sm leading-snug font-bold text-white">
                    {item.name} <span className="ml-1 rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-extrabold">{item.quantity}x</span>
                </p>
                {details.length > 0 && <p className="text-[11px] text-ink-400">{details.join(' • ')}</p>}
                {item.combo_name && (
                    <p className="text-[11px] font-semibold text-go-400">
                        ✓ Upgraded to {item.combo_name}
                        {item.combo_choice && ` (${item.combo_choice})`}
                    </p>
                )}
                {item.notes && <p className="text-[11px] text-flame-300">Note: {item.notes}</p>}
            </div>
            <div className="shrink-0 text-right">
                <p className="text-sm font-extrabold text-flame-400">{money(item.line_total)}</p>
                {item.quantity > 1 && <p className="text-[11px] text-ink-500">{money(item.unit_price)} ea</p>}
                {showKitchen && <KitchenStatusBadge status={item.kitchen_status} label={item.kitchen_status_label} />}
            </div>
        </li>
    );
}

const stationLabels: Record<KitchenItemStatus, string> = { queued: 'Queued', cooking: 'Cooking', done: 'Completed' };

/** Per-station progress of this order, derived from its items. */
function StationMonitor({ items, idle }: { items: OrderItem[]; idle: boolean }) {
    const stations = new Map<string, OrderItem[]>();
    items.forEach((item) => {
        const name = item.station?.name ?? 'Kitchen';
        stations.set(name, [...(stations.get(name) ?? []), item]);
    });

    return (
        <Card>
            <CardHeader icon={<ChefHat className="size-4" />} title="Kitchen Station Monitor" action={<Badge tone="green">Live</Badge>} />
            <ul className="divide-y divide-ink-800">
                {[...stations.entries()].map(([name, stationItems], index) => {
                    const total = stationItems.reduce((sum, item) => sum + item.quantity, 0);
                    const done = stationItems.filter((item) => item.kitchen_status === 'done').reduce((sum, item) => sum + item.quantity, 0);
                    const cooking = stationItems.some((item) => item.kitchen_status === 'cooking');
                    const status: KitchenItemStatus = done === total ? 'done' : cooking || done > 0 ? 'cooking' : 'queued';
                    const progress = Math.round(((done + (cooking ? 0.5 : 0)) / total) * 100);

                    return (
                        <li key={name} className="space-y-2 px-4 py-3.5">
                            <div className="flex items-center justify-between gap-2">
                                <p className="flex items-center gap-2 text-sm font-bold text-white">
                                    <span className={cn('size-2 rounded-full', status === 'done' ? 'bg-go-400' : status === 'cooking' ? 'animate-pulse-soft bg-brand-500' : 'bg-ink-500')} />
                                    Station {index + 1}: {name}
                                </p>
                                <KitchenStatusBadge status={status} label={idle ? 'Waiting' : stationLabels[status]} />
                            </div>
                            <p className="text-xs text-ink-400">{stationItems.map((item) => `${item.quantity}× ${item.name}`).join(', ')}</p>
                            <div className="h-1.5 overflow-hidden rounded-full bg-ink-700" role="progressbar" aria-valuenow={idle ? 0 : progress} aria-valuemin={0} aria-valuemax={100} aria-label={`${name} progress`}>
                                <div className={cn('h-full rounded-full transition-all duration-500', status === 'done' ? 'bg-go-400' : 'bg-brand-500')} style={{ width: `${idle ? 0 : Math.min(100, progress)}%` }} />
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}
