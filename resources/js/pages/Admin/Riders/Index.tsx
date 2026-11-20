import { Head, router, useForm } from '@inertiajs/react';
import { Bike, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { RowActions } from '@/components/admin/RowActions';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';

interface Rider {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    vehicle_type: string;
    plate_number: string | null;
    is_available: boolean;
    is_active: boolean;
    deliveries_count: number;
    active_deliveries_count: number;
}

export default function RidersIndex({ riders }: { riders: Rider[] }) {
    const [editing, setEditing] = useState<Rider | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Riders"
            description="Delivery riders and their accounts"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New rider
                </Button>
            }
        >
            <Head title="Riders" />

            <Card>
                {riders.length === 0 ? (
                    <EmptyState icon={<Bike className="size-8" />} title="No riders yet">
                        Add a rider so delivery orders can be dispatched from the counter.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Rider</Th>
                                <Th>Contact</Th>
                                <Th>Vehicle</Th>
                                <Th>Deliveries</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {riders.map((rider) => (
                                <tr key={rider.id} className="hover:bg-ink-800/60">
                                    <Td className="font-bold text-strong">{rider.name}</Td>
                                    <Td>
                                        {rider.phone ? `+${rider.phone}` : '—'}
                                        <span className="block text-xs text-ink-400">{rider.email}</span>
                                    </Td>
                                    <Td>
                                        {rider.vehicle_type}
                                        {rider.plate_number && <span className="block text-xs text-ink-400">{rider.plate_number}</span>}
                                    </Td>
                                    <Td>
                                        {rider.deliveries_count} total
                                        {rider.active_deliveries_count > 0 && <span className="block text-xs font-semibold text-flame-400">{rider.active_deliveries_count} in progress</span>}
                                    </Td>
                                    <Td>
                                        <div className="flex flex-wrap gap-1">
                                            <ActiveBadge active={rider.is_active} />
                                            {rider.is_active && <Badge tone={rider.is_available ? 'blue' : 'neutral'}>{rider.is_available ? 'On shift' : 'Off shift'}</Badge>}
                                        </div>
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={rider.name}
                                            onEdit={() => setEditing(rider)}
                                            onDelete={() => router.delete(route('admin.riders.destroy', rider.id), { preserveScroll: true })}
                                            deleteNote={
                                                rider.deliveries_count > 0
                                                    ? 'This rider has delivery history, so the account will be deactivated instead of deleted.'
                                                    : 'The rider and their login are removed.'
                                            }
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && <RiderForm key={editing === 'new' ? 'new' : editing.id} rider={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function RiderForm({ rider, onClose }: { rider: Rider | null; onClose: () => void }) {
    const form = useForm({
        name: rider?.name ?? '',
        email: rider?.email ?? '',
        phone: rider?.phone ?? '',
        password: '',
        vehicle_type: rider?.vehicle_type ?? 'Motorcycle',
        plate_number: rider?.plate_number ?? '',
        is_available: rider?.is_available ?? true,
        is_active: rider?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (rider) {
            form.put(route('admin.riders.update', rider.id), options);
        } else {
            form.post(route('admin.riders.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={rider ? `Edit ${rider.name}` : 'New rider'}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <Input label="Full name" required autoFocus value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                    <Input label="Mobile number" type="tel" required placeholder="012-345 6789" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} error={form.errors.phone} />
                    <Input label="Email (login)" type="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} error={form.errors.email} />
                    <Input
                        label={rider ? 'New password' : 'Password'}
                        type="password"
                        autoComplete="new-password"
                        required={!rider}
                        hint={rider ? 'Leave empty to keep the current password.' : 'At least 8 characters.'}
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        error={form.errors.password}
                    />
                    <Input label="Vehicle" required value={form.data.vehicle_type} onChange={(event) => form.setData('vehicle_type', event.target.value)} error={form.errors.vehicle_type} />
                    <Input label="Plate number" placeholder="WXY 1234" value={form.data.plate_number} onChange={(event) => form.setData('plate_number', event.target.value)} error={form.errors.plate_number} />
                </div>
                <Toggle checked={form.data.is_available} onChange={(checked) => form.setData('is_available', checked)} label="On shift" description="Only riders on shift can be assigned deliveries." />
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Account active" description="Inactive riders cannot sign in." />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {rider ? 'Save changes' : 'Add rider'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
