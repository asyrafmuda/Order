import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Printer, QrCode, RefreshCw } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { RowActions } from '@/components/admin/RowActions';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Input, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';

export interface DiningTable {
    id: number;
    name: string;
    seats: number | null;
    sort_order: number;
    is_active: boolean;
    open_orders: number;
    /** What the table's QR code opens. */
    url: string;
}

export default function TablesIndex({ tables }: { tables: DiningTable[] }) {
    const [editing, setEditing] = useState<DiningTable | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Tables & QR"
            description="Customers scan the code on their table and order from their seat — no app, no sign-in"
            actions={
                <>
                    <LinkButton href={route('admin.tables.print')} variant="secondary" size="sm" icon={<Printer className="size-4" aria-hidden />}>
                        Print QR codes
                    </LinkButton>
                    <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                        New table
                    </Button>
                </>
            }
        >
            <Head title="Tables & QR" />

            {tables.length === 0 ? (
                <Card>
                    <EmptyState icon={<QrCode className="size-8" />} title="No tables yet">
                        Add your tables, print their QR codes and stick one on each table.
                    </EmptyState>
                </Card>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {tables.map((table) => (
                        <Card key={table.id} className="flex gap-4 p-4">
                            <div className="shrink-0 rounded-lg bg-white p-2">
                                <QRCodeSVG value={table.url} size={96} aria-label={`QR code for ${table.name}`} />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="font-display text-3xl leading-none text-white uppercase">{table.name}</p>
                                    <ActiveBadge active={table.is_active} />
                                </div>
                                <p className="mt-1 text-xs text-ink-400">
                                    {table.seats ? `${table.seats} seats` : 'Seats not set'}
                                    {table.open_orders > 0 && (
                                        <Badge tone="solid-red" className="ml-2">
                                            {table.open_orders} open
                                        </Badge>
                                    )}
                                </p>
                                <a href={table.url} target="_blank" rel="noreferrer" className="mt-1 truncate text-[11px] text-ink-500 hover:text-flame-400" title="Open what a customer sees after scanning">
                                    {table.url.replace(/^https?:\/\//, '')}
                                </a>
                                <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-3">
                                    <ConfirmButton
                                        variant="ghost"
                                        size="xs"
                                        title={`Issue a new QR code for ${table.name}?`}
                                        message="The printed code on the table stops working straight away, so reprint it. Use this if a code was photographed and is being misused."
                                        confirmLabel="Issue new code"
                                        onConfirm={() => router.post(route('admin.tables.regenerate', table.id), {}, { preserveScroll: true })}
                                        icon={<RefreshCw className="size-3.5" aria-hidden />}
                                    >
                                        New code
                                    </ConfirmButton>
                                    <span className="ml-auto">
                                        <RowActions
                                            name={table.name}
                                            onEdit={() => setEditing(table)}
                                            onDelete={() => router.delete(route('admin.tables.destroy', table.id), { preserveScroll: true })}
                                            deleteNote="Its QR code stops working. Past orders keep the table name."
                                        />
                                    </span>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {editing && <TableForm key={editing === 'new' ? 'new' : editing.id} table={editing === 'new' ? null : editing} nextNumber={tables.length + 1} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function TableForm({ table, nextNumber, onClose }: { table: DiningTable | null; nextNumber: number; onClose: () => void }) {
    const form = useForm({
        name: table?.name ?? `Table ${nextNumber}`,
        seats: table?.seats != null ? String(table.seats) : '4',
        sort_order: String(table?.sort_order ?? nextNumber),
        is_active: table?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        form.transform((data) => ({ ...data, seats: data.seats === '' ? null : data.seats }));

        if (table) {
            form.put(route('admin.tables.update', table.id), options);
        } else {
            form.post(route('admin.tables.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={table ? `Edit ${table.name}` : 'New table'} size="sm">
            <form onSubmit={submit} className="space-y-4">
                <Input label="Name" required autoFocus placeholder="Table 5" hint="Shown to the customer and on the kitchen ticket." value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <div className="grid grid-cols-2 gap-4">
                    <Input label="Seats" type="number" min={1} max={50} value={form.data.seats} onChange={(event) => form.setData('seats', event.target.value)} error={form.errors.seats} />
                    <Input label="Sort order" type="number" min={0} required value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} error={form.errors.sort_order} />
                </div>
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Active" description="Scanning an inactive table's code does not start an order." />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {table ? 'Save changes' : 'Add table'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
