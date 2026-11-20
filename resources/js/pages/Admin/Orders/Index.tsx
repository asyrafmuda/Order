import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useFilters } from '@/components/admin/useFilters';
import { Badge, OrderStatusBadge, PaymentStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Form';
import { Pagination } from '@/components/ui/Pagination';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { dateTime, money, plural } from '@/lib/format';
import type { Option, Order, Paginated } from '@/types';

interface OrdersIndexProps {
    orders: Paginated<Order>;
    filters: { search: string; status: string; type: string; date: string };
    statuses: Option[];
    types: Option[];
}

export default function OrdersIndex({ orders, filters, statuses, types }: OrdersIndexProps) {
    const { values, set, reset, active } = useFilters('admin.orders.index', filters);

    return (
        <BackOfficeLayout title="Orders" description={`${orders.total} matching ${orders.total === 1 ? 'order' : 'orders'}`}>
            <Head title="Orders" />

            <Card className="mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_1fr_auto] lg:items-end">
                <Input
                    label="Search"
                    type="search"
                    placeholder="Order number, name or phone"
                    prefix={<Search className="size-4" aria-hidden />}
                    value={values.search}
                    onChange={(event) => set('search', event.target.value)}
                />
                <Select label="Status" value={values.status} onChange={(event) => set('status', event.target.value)}>
                    <option value="">All statuses</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
                <Select label="Type" value={values.type} onChange={(event) => set('type', event.target.value)}>
                    <option value="">All types</option>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </Select>
                <Input label="Date" type="date" value={values.date} onChange={(event) => set('date', event.target.value)} />
                <Button variant="ghost" onClick={reset} disabled={!active}>
                    Clear
                </Button>
            </Card>

            <Card>
                {orders.data.length === 0 ? (
                    <EmptyState icon={<ClipboardList className="size-8" />} title="No orders found">
                        {active ? 'Try widening the filters.' : 'Orders appear here as soon as customers place them.'}
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Order</Th>
                                <Th>Placed</Th>
                                <Th>Customer</Th>
                                <Th>Type</Th>
                                <Th>Status</Th>
                                <Th>Payment</Th>
                                <Th>POS</Th>
                                <Th className="text-right">Total</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr key={order.uuid} className="hover:bg-ink-800/60">
                                    <Td>
                                        <Link href={route('admin.orders.show', order.uuid)} className="font-extrabold text-strong hover:text-flame-400">
                                            #{order.order_number}
                                        </Link>
                                        <span className="block text-xs text-ink-400">{plural(order.items_count ?? 0, 'item')}</span>
                                    </Td>
                                    <Td className="text-xs whitespace-nowrap text-ink-300">{dateTime(order.created_at)}</Td>
                                    <Td>
                                        {order.customer_name}
                                        <span className="block text-xs text-ink-400">{order.customer_phone ? `+${order.customer_phone}` : order.source_label}</span>
                                    </Td>
                                    <Td>
                                        <Badge tone={order.type === 'delivery' ? 'blue' : 'neutral'}>{order.type_label}</Badge>
                                    </Td>
                                    <Td>
                                        <OrderStatusBadge status={order.status} label={order.status_label} />
                                    </Td>
                                    <Td>
                                        <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                                        <span className="mt-0.5 block text-xs text-ink-400">{order.payment_method_label}</span>
                                    </Td>
                                    <Td>
                                        <Badge tone={order.pos.status === 'synced' ? 'green' : order.pos.status === 'failed' ? 'red' : 'neutral'}>{order.pos.status}</Badge>
                                    </Td>
                                    <Td className="text-right font-bold whitespace-nowrap text-flame-400">{money(order.total)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
                <Pagination page={orders} />
            </Card>
        </BackOfficeLayout>
    );
}
