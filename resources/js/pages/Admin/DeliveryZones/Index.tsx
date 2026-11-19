import { Head, router, useForm } from '@inertiajs/react';
import { MapPinned, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { RowActions } from '@/components/admin/RowActions';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { inputToSen, money, senToInput } from '@/lib/format';
import type { DeliveryZone } from '@/types';

export default function DeliveryZonesIndex({ zones }: { zones: DeliveryZone[] }) {
    const [editing, setEditing] = useState<DeliveryZone | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Delivery Zones"
            description="Areas you deliver to, with their fee, minimum order and delivery time"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New zone
                </Button>
            }
        >
            <Head title="Delivery zones" />

            <Card>
                {zones.length === 0 ? (
                    <EmptyState icon={<MapPinned className="size-8" />} title="No delivery zones">
                        Without an active zone customers can only choose self pickup.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Order</Th>
                                <Th>Zone</Th>
                                <Th>Fee</Th>
                                <Th>Minimum order</Th>
                                <Th>Delivery time</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {zones.map((zone) => (
                                <tr key={zone.id} className="hover:bg-ink-800/60">
                                    <Td className="w-16 text-ink-400">{zone.sort_order}</Td>
                                    <Td>
                                        <span className="font-bold text-white">{zone.name}</span>
                                        {zone.areas && <span className="block text-xs text-ink-400">{zone.areas}</span>}
                                    </Td>
                                    <Td className="font-bold text-flame-400">{money(zone.fee)}</Td>
                                    <Td>{zone.min_order > 0 ? money(zone.min_order) : 'None'}</Td>
                                    <Td>{zone.eta_minutes} min</Td>
                                    <Td>
                                        <ActiveBadge active={zone.is_active} />
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={zone.name}
                                            onEdit={() => setEditing(zone)}
                                            onDelete={() => router.delete(route('admin.delivery-zones.destroy', zone.id), { preserveScroll: true })}
                                            deleteNote="Customers will no longer be able to choose this area. Past deliveries keep their details."
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && <ZoneForm key={editing === 'new' ? 'new' : editing.id} zone={editing === 'new' ? null : editing} nextSort={zones.length} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function ZoneForm({ zone, nextSort, onClose }: { zone: DeliveryZone | null; nextSort: number; onClose: () => void }) {
    const form = useForm({
        name: zone?.name ?? '',
        areas: zone?.areas ?? '',
        fee: zone ? senToInput(zone.fee) : '',
        min_order: zone ? senToInput(zone.min_order) : '0.00',
        eta_minutes: String(zone?.eta_minutes ?? 40),
        sort_order: String(zone?.sort_order ?? nextSort),
        is_active: zone?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, fee: inputToSen(data.fee), min_order: inputToSen(data.min_order) }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (zone) {
            form.put(route('admin.delivery-zones.update', zone.id), options);
        } else {
            form.post(route('admin.delivery-zones.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={zone ? `Edit ${zone.name}` : 'New delivery zone'}>
            <form onSubmit={submit} className="space-y-4">
                <Input label="Name" required autoFocus placeholder="Zone A — Nearby" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <Input
                    label="Coverage"
                    placeholder="Within 3 km of the shop"
                    hint="Shown to customers so they can pick the right area."
                    value={form.data.areas}
                    onChange={(event) => form.setData('areas', event.target.value)}
                    error={form.errors.areas}
                />
                <div className="grid gap-3 sm:grid-cols-2">
                    <MoneyInput label="Delivery fee" required value={form.data.fee} onChange={(value) => form.setData('fee', value)} error={form.errors.fee} />
                    <MoneyInput label="Minimum order" required value={form.data.min_order} onChange={(value) => form.setData('min_order', value)} error={form.errors.min_order} />
                    <Input label="Delivery time (min)" type="number" min={10} max={240} required value={form.data.eta_minutes} onChange={(event) => form.setData('eta_minutes', event.target.value)} error={form.errors.eta_minutes} />
                    <Input label="Sort order" type="number" min={0} required value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} error={form.errors.sort_order} />
                </div>
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Accepting deliveries" />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {zone ? 'Save changes' : 'Create zone'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
