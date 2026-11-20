import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Printer, QrCode, RefreshCw, Store } from 'lucide-react';
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

/** The outlet's own code for the counter or kiosk. */
export interface ShopCode {
    /** What the code opens. */
    url: string;
    outlet: string;
    /** False while the outlet is closed to customers or does not take pickup orders. */
    usable: boolean;
}

export default function TablesIndex({ tables, shopCode }: { tables: DiningTable[]; shopCode: ShopCode }) {
    const [editing, setEditing] = useState<DiningTable | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Tables & QR"
            description="Customers scan a code at the counter or on their table and order on their phone — no app, no sign-in"
            actions={
                <>
                    <LinkButton href={route('admin.tables.print')} variant="secondary" size="sm" icon={<Printer className="size-4" aria-hidden />}>
                        Print table codes
                    </LinkButton>
                    <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                        New table
                    </Button>
                </>
            }
        >
            <Head title="Tables & QR" />

            <Card className="mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                <div className="shrink-0 self-start rounded-lg bg-white p-2">
                    <QRCodeSVG value={shopCode.url} size={112} aria-label="Shop QR code" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-2 text-sm font-bold text-strong">
                        <Store className="size-4 shrink-0 text-flame-400" aria-hidden /> Shop QR code
                        {!shopCode.usable && <Badge tone="red">Not in use</Badge>}
                    </p>
                    <p className="mt-1 text-sm text-ink-300">
                        One code for the counter or kiosk. Customers scan it, order on their phone and collect when their number is called — no name, no sign-in, and they can pay you on the spot.
                        No tables needed.
                    </p>
                    {!shopCode.usable && <p className="mt-1 text-xs font-medium text-flame-300">Scanning does nothing right now: this outlet is inactive or self pickup is switched off.</p>}
                    <a href={shopCode.url} target="_blank" rel="noreferrer" className="mt-1 block truncate text-[11px] text-ink-500 hover:text-flame-400" title="Open what a customer sees after scanning">
                        {shopCode.url.replace(/^https?:\/\//, '')}
                    </a>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <LinkButton href={route('admin.shop-qr.print')} size="sm" icon={<Printer className="size-4" aria-hidden />}>
                            Print poster
                        </LinkButton>
                        <ConfirmButton
                            variant="ghost"
                            size="sm"
                            title="Issue a new shop QR code?"
                            message="The printed poster stops working straight away, so reprint it. Use this if the code was shared around and is being misused."
                            confirmLabel="Issue new code"
                            onConfirm={() => router.post(route('admin.shop-qr.regenerate'), {}, { preserveScroll: true })}
                            icon={<RefreshCw className="size-4" aria-hidden />}
                        >
                            New code
                        </ConfirmButton>
                    </div>
                </div>
            </Card>

            <h2 className="mb-3 text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">Tables (dine-in)</h2>

            {tables.length === 0 ? (
                <Card>
                    <EmptyState icon={<QrCode className="size-8" />} title="No tables yet">
                        Only for shops with seating: add your tables, print their QR codes and stick one on each table. A kiosk or takeaway counter needs just the shop code above.
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
                                    <p className="font-display text-3xl leading-none text-strong uppercase">{table.name}</p>
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
