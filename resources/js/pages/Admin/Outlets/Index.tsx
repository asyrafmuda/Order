import { Head, Link, router } from '@inertiajs/react';
import { Building2, Eye, Plus } from 'lucide-react';
import { useState } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { OutletForm, type Outlet } from '@/components/admin/OutletForm';
import { RowActions } from '@/components/admin/RowActions';
import { Badge } from '@/components/ui/Badge';
import { Button, buttonClass } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { money } from '@/lib/format';

interface OutletRow extends Outlet {
    tables_count: number;
    staff_count: number;
    orders_today: number;
    sales_today: number;
}

export default function OutletsIndex({ outlets, multi }: { outlets: OutletRow[]; multi: boolean }) {
    const [editing, setEditing] = useState<OutletRow | 'new' | null>(null);
    const [saving, setSaving] = useState(false);
    const main = outlets.find((outlet) => outlet.is_default) ?? outlets[0];

    return (
        <BackOfficeLayout
            title="Outlets"
            description="Every branch of the business, with its own orders, tables, staff and opening hours"
            actions={
                multi && (
                    <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                        New outlet
                    </Button>
                )
            }
        >
            <Head title="Outlets" />

            {!multi && (
                <Card className="mb-4 flex flex-wrap items-center gap-3 border-flame-500/40 bg-flame-900/40 p-4">
                    <p className="min-w-0 flex-1 text-sm text-flame-300">
                        Multi-outlet is switched off, so the business runs as one outlet. Switch it on to add more branches.
                    </p>
                    <Link href={route('admin.settings.edit')} className={buttonClass('flame', 'sm')}>
                        Open settings
                    </Link>
                </Card>
            )}

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {outlets.map((outlet) => (
                    <Card key={outlet.id} className="flex flex-col p-4">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="truncate font-display text-3xl leading-none text-white uppercase">{outlet.name}</p>
                                <p className="mt-1 text-xs text-ink-400">{outlet.settings.shop.address}</p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                {outlet.is_default && <Badge tone="flame">Main</Badge>}
                                <ActiveBadge active={outlet.is_active} />
                            </div>
                        </div>

                        <dl className="mt-4 grid grid-cols-2 gap-2 text-center">
                            <Figure label="Sales today" value={money(outlet.sales_today)} highlight />
                            <Figure label="Orders today" value={String(outlet.orders_today)} />
                            <Figure label="Tables" value={String(outlet.tables_count)} />
                            <Figure label="Staff" value={String(outlet.staff_count)} />
                        </dl>

                        <p className="mt-3 text-xs text-ink-400">
                            {outlet.settings.hours.open} – {outlet.settings.hours.close} ·{' '}
                            {[outlet.settings.ordering.pickup_enabled && 'Pickup', outlet.settings.ordering.delivery_enabled && 'Delivery', outlet.settings.ordering.dine_in_enabled && 'Dine-in']
                                .filter(Boolean)
                                .join(', ') || 'No ordering'}
                            {!outlet.settings.ordering.enabled && ' (online ordering paused)'}
                        </p>

                        <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-4">
                            {multi && (
                                <Button variant="ghost" size="xs" onClick={() => router.post(route('admin.outlets.switch'), { outlet_id: outlet.id }, { preserveScroll: true })} icon={<Eye className="size-3.5" aria-hidden />}>
                                    View this outlet
                                </Button>
                            )}
                            <span className="ml-auto">
                                <RowActions
                                    name={outlet.name}
                                    onEdit={() => setEditing(outlet)}
                                    onDelete={() => router.delete(route('admin.outlets.destroy', outlet.id), { preserveScroll: true })}
                                    deleteDisabled={outlet.is_default}
                                    deleteNote="Only an outlet that never took an order can be removed; its tables and delivery areas go with it. Otherwise deactivate it."
                                />
                            </span>
                        </div>
                    </Card>
                ))}
            </div>

            {outlets.length === 0 && (
                <Card className="p-10 text-center text-sm text-ink-400">
                    <Building2 className="mx-auto mb-2 size-8 text-ink-600" aria-hidden /> No outlets yet.
                </Card>
            )}

            {editing && (
                <Modal
                    open
                    size="lg"
                    onClose={() => setEditing(null)}
                    title={editing === 'new' ? 'New outlet' : `Edit ${editing.name}`}
                    footer={
                        <>
                            <Button variant="ghost" onClick={() => setEditing(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" form="outlet-form" loading={saving}>
                                {editing === 'new' ? 'Add outlet' : 'Save changes'}
                            </Button>
                        </>
                    }
                >
                    <OutletForm
                        key={editing === 'new' ? 'new' : editing.id}
                        formId="outlet-form"
                        outlet={editing === 'new' ? null : editing}
                        // A new outlet starts from the main outlet's hours and settings.
                        template={main.settings}
                        mode={editing === 'new' ? 'create' : 'update'}
                        identity
                        onSuccess={() => setEditing(null)}
                        onProcessing={setSaving}
                    />
                </Modal>
            )}
        </BackOfficeLayout>
    );
}

function Figure({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div className="rounded-lg bg-ink-900 px-2 py-2">
            <dt className="text-[10px] font-bold tracking-wider text-ink-400 uppercase">{label}</dt>
            <dd className={`font-display text-2xl leading-none whitespace-nowrap ${highlight ? 'text-flame-400' : 'text-white'}`}>{value}</dd>
        </div>
    );
}
