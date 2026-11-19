import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, MessageSquareText, Plug, RefreshCw, Wifi } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { dateTime } from '@/lib/format';

interface PosLog {
    id: number;
    provider: string;
    action: string;
    status: string;
    error: string | null;
    order_number: string | null;
    order_uuid: string | null;
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
    order_number: string | null;
    order_uuid: string | null;
    at: string | null;
}

interface IntegrationsProps {
    pos: { provider: string; configured: boolean; products_linked: number; products_unlinked: number; orders_failed: number; webhook_url: string };
    payment: { gateway: string; callback_url: string };
    messaging: { channel: string };
    posLogs: PosLog[];
    notificationLogs: NotificationLog[];
}

const statusTone = (status: string) => (status === 'synced' || status === 'sent' ? 'green' : status === 'failed' ? 'red' : 'neutral');

export default function IntegrationsIndex({ pos, payment, messaging, posLogs, notificationLogs }: IntegrationsProps) {
    const [busy, setBusy] = useState<'test' | 'sync' | null>(null);

    const run = (action: 'test' | 'sync') =>
        router.post(route(action === 'test' ? 'admin.integrations.pos.test' : 'admin.integrations.pos.sync-menu'), {}, { preserveScroll: true, onStart: () => setBusy(action), onFinish: () => setBusy(null) });

    return (
        <BackOfficeLayout title="Integrations" description="POS, payment gateway and customer messaging">
            <Head title="Integrations" />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card>
                    <CardHeader title="Point of sale" icon={<Plug className="size-4" />} action={<Badge tone={pos.configured ? 'green' : 'red'}>{pos.configured ? 'Configured' : 'Not configured'}</Badge>} />
                    <dl className="space-y-2 px-4 py-3 text-sm">
                        <Row label="Provider" value={<span className="font-bold text-white capitalize">{pos.provider}</span>} />
                        <Row label="Products linked" value={`${pos.products_linked} of ${pos.products_linked + pos.products_unlinked}`} />
                        <Row
                            label="Orders failing to sync"
                            value={
                                pos.orders_failed > 0 ? (
                                    <Link href={route('admin.orders.index')} className="font-bold text-brand-400 hover:underline">
                                        {pos.orders_failed}
                                    </Link>
                                ) : (
                                    '0'
                                )
                            }
                        />
                    </dl>
                    <div className="flex flex-wrap gap-2 border-t border-ink-700 p-4">
                        <Button variant="secondary" size="sm" onClick={() => run('test')} loading={busy === 'test'} disabled={busy !== null} icon={<Wifi className="size-4" aria-hidden />}>
                            Test connection
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => run('sync')} loading={busy === 'sync'} disabled={busy !== null} icon={<RefreshCw className="size-4" aria-hidden />}>
                            Sync menu
                        </Button>
                    </div>
                    <p className="border-t border-ink-700 px-4 py-3 text-xs leading-relaxed text-ink-400">
                        Set <code className="text-ink-200">POS_PROVIDER</code> and the Loyverse keys in <code className="text-ink-200">.env</code>. “Sync menu” links products to POS items by SKU (product code) or
                        name. Webhook URL: <code className="break-all text-ink-200">{pos.webhook_url}</code>
                    </p>
                </Card>

                <Card>
                    <CardHeader title="Payment gateway" icon={<CreditCard className="size-4" />} action={<Badge tone={payment.gateway === 'fake' ? 'flame' : 'green'}>{payment.gateway === 'fake' ? 'Simulated' : 'Live'}</Badge>} />
                    <dl className="space-y-2 px-4 py-3 text-sm">
                        <Row label="Driver" value={<span className="font-bold text-white capitalize">{payment.gateway}</span>} />
                    </dl>
                    <p className="border-t border-ink-700 px-4 py-3 text-xs leading-relaxed text-ink-400">
                        {payment.gateway === 'fake'
                            ? 'Customers see a simulated bank page — no real money moves. Add a real driver and set PAYMENT_GATEWAY in .env to go live.'
                            : 'Online payments are processed by the configured gateway.'}{' '}
                        Callback URL: <code className="break-all text-ink-200">{payment.callback_url}</code>
                    </p>
                </Card>

                <Card>
                    <CardHeader title="Customer messaging" icon={<MessageSquareText className="size-4" />} action={<Badge tone={messaging.channel === 'log' ? 'flame' : 'green'}>{messaging.channel === 'log' ? 'Log only' : 'Live'}</Badge>} />
                    <dl className="space-y-2 px-4 py-3 text-sm">
                        <Row label="Channel" value={<span className="font-bold text-white capitalize">{messaging.channel}</span>} />
                    </dl>
                    <p className="border-t border-ink-700 px-4 py-3 text-xs leading-relaxed text-ink-400">
                        {messaging.channel === 'log'
                            ? 'Order updates are written to the application log instead of being sent. Set MESSAGING_CHANNEL=whatsapp and the WhatsApp keys in .env to send them.'
                            : 'Order updates are sent to the customer’s phone.'}{' '}
                        Messages can be switched off in{' '}
                        <Link href={route('admin.settings.edit')} className="font-semibold text-flame-400 hover:underline">
                            Settings
                        </Link>
                        .
                    </p>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader title="POS activity — latest 30" icon={<Plug className="size-4" />} />
                {posLogs.length === 0 ? (
                    <EmptyState title="No POS activity yet">Receipts pushed to the POS and menu syncs are logged here.</EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>When</Th>
                                <Th>Action</Th>
                                <Th>Order</Th>
                                <Th>Status</Th>
                                <Th>Details</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {posLogs.map((log) => (
                                <tr key={log.id}>
                                    <Td className="text-xs whitespace-nowrap text-ink-300">{dateTime(log.at)}</Td>
                                    <Td>
                                        {log.action}
                                        <span className="block text-xs text-ink-400">{log.provider}</span>
                                    </Td>
                                    <Td>
                                        {log.order_uuid ? (
                                            <Link href={route('admin.orders.show', log.order_uuid)} className="font-bold text-white hover:text-flame-400">
                                                #{log.order_number}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </Td>
                                    <Td>
                                        <Badge tone={statusTone(log.status)}>{log.status}</Badge>
                                    </Td>
                                    <Td className="max-w-md text-xs whitespace-normal text-brand-400">{log.error}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Card className="mt-6">
                <CardHeader title="Customer messages — latest 30" icon={<MessageSquareText className="size-4" />} />
                {notificationLogs.length === 0 ? (
                    <EmptyState title="No messages yet">Order updates sent to customers are logged here.</EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>When</Th>
                                <Th>Order</Th>
                                <Th>To</Th>
                                <Th>Status</Th>
                                <Th>Message</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {notificationLogs.map((log) => (
                                <tr key={log.id}>
                                    <Td className="text-xs whitespace-nowrap text-ink-300">{dateTime(log.at)}</Td>
                                    <Td>
                                        {log.order_uuid ? (
                                            <Link href={route('admin.orders.show', log.order_uuid)} className="font-bold text-white hover:text-flame-400">
                                                #{log.order_number}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                        <span className="block text-xs text-ink-400">{log.template}</span>
                                    </Td>
                                    <Td className="whitespace-nowrap">
                                        +{log.recipient}
                                        <span className="block text-xs text-ink-400">via {log.channel}</span>
                                    </Td>
                                    <Td>
                                        <Badge tone={statusTone(log.status)}>{log.status}</Badge>
                                    </Td>
                                    <Td className="max-w-lg text-xs leading-relaxed whitespace-pre-line text-ink-300">
                                        {log.message}
                                        {log.error && <span className="mt-1 block text-brand-400">{log.error}</span>}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </BackOfficeLayout>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-ink-400">{label}</dt>
            <dd className="text-right text-ink-100">{value}</dd>
        </div>
    );
}
