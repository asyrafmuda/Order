import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { RowActions } from '@/components/admin/RowActions';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';

interface Station {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    categories_count: number;
    products_count: number;
}

export default function KitchenStationsIndex({ stations }: { stations: Station[] }) {
    const [editing, setEditing] = useState<Station | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Kitchen Stations"
            description="Where each item is cooked; the kitchen display groups tickets by station"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New station
                </Button>
            }
        >
            <Head title="Kitchen stations" />

            <Card>
                {stations.length === 0 ? (
                    <EmptyState icon={<Layers className="size-8" />} title="No stations yet">
                        Add a station such as “Grill” or “Drinks” and assign categories to it.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Order</Th>
                                <Th>Station</Th>
                                <Th>Categories</Th>
                                <Th>Product overrides</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {stations.map((station) => (
                                <tr key={station.id} className="hover:bg-ink-800/60">
                                    <Td className="w-16 text-ink-400">{station.sort_order}</Td>
                                    <Td className="font-bold text-white">{station.name}</Td>
                                    <Td>{station.categories_count}</Td>
                                    <Td>{station.products_count}</Td>
                                    <Td>
                                        <ActiveBadge active={station.is_active} />
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={station.name}
                                            onEdit={() => setEditing(station)}
                                            onDelete={() => router.delete(route('admin.kitchen-stations.destroy', station.id), { preserveScroll: true })}
                                            deleteNote="Stations that still cook menu items cannot be deleted. Reassign them first or deactivate the station."
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && <StationForm key={editing === 'new' ? 'new' : editing.id} station={editing === 'new' ? null : editing} nextSort={stations.length} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function StationForm({ station, nextSort, onClose }: { station: Station | null; nextSort: number; onClose: () => void }) {
    const form = useForm({
        name: station?.name ?? '',
        sort_order: String(station?.sort_order ?? nextSort),
        is_active: station?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (station) {
            form.put(route('admin.kitchen-stations.update', station.id), options);
        } else {
            form.post(route('admin.kitchen-stations.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={station ? `Edit ${station.name}` : 'New station'} size="sm">
            <form onSubmit={submit} className="space-y-4">
                <Input label="Name" required autoFocus placeholder="e.g. Grill, Fryer, Drinks" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <Input label="Sort order" type="number" min={0} required value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} error={form.errors.sort_order} />
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Active" description="Inactive stations are hidden from the kitchen display filters." />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {station ? 'Save changes' : 'Create station'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
