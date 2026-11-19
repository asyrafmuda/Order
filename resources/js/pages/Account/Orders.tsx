import { Head, Link } from '@inertiajs/react';
import { ChevronRight, ReceiptText } from 'lucide-react';
import { AccountTabs } from '@/components/storefront/AccountTabs';
import { OrderStatusBadge } from '@/components/ui/Badge';
import { buttonClass } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Pagination } from '@/components/ui/Pagination';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { dateTime, money } from '@/lib/format';
import type { Order, Paginated } from '@/types';

export default function AccountOrders({ orders }: { orders: Paginated<Order> }) {
    return (
        <StorefrontLayout>
            <Head title="My orders" />
            <AccountTabs />

            <Card>
                {orders.data.length === 0 ? (
                    <EmptyState icon={<ReceiptText className="size-8" />} title="No orders yet">
                        <p className="mb-4">Your order history shows up here after your first order.</p>
                        <Link href={route('menu.index')} className={buttonClass('primary', 'md')}>
                            Browse the menu
                        </Link>
                    </EmptyState>
                ) : (
                    <>
                        <ul className="divide-y divide-ink-800">
                            {orders.data.map((order) => (
                                <li key={order.uuid}>
                                    <Link href={route('orders.show', order.uuid)} className="flex items-center gap-4 px-4 py-4 hover:bg-ink-800">
                                        <span className="w-20 shrink-0 font-display text-3xl leading-none text-brand-500">#{order.order_number}</span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold text-white">{(order.items ?? []).map((item) => `${item.quantity}× ${item.name}`).join(', ')}</span>
                                            <span className="text-xs text-ink-400">
                                                {dateTime(order.created_at)} · {order.type_label} · {order.payment_method_label}
                                            </span>
                                        </span>
                                        <span className="hidden text-sm font-bold text-flame-400 sm:block">{money(order.total)}</span>
                                        <OrderStatusBadge status={order.status} label={order.status_label} />
                                        <ChevronRight className="size-4 shrink-0 text-ink-500" aria-hidden />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <Pagination page={orders} />
                    </>
                )}
            </Card>
        </StorefrontLayout>
    );
}
