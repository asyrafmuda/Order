import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, MapPin, Pencil, Plus, Trash2, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { AccountTabs } from '@/components/storefront/AccountTabs';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Checkbox, Input, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import StorefrontLayout from '@/layouts/StorefrontLayout';

interface Address {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    line1: string;
    line2: string | null;
    city: string;
    postcode: string;
    notes: string | null;
    delivery_zone_id: number | null;
    is_default: boolean;
}

interface ProfileProps {
    addresses: Address[];
    zones: { id: number; name: string }[];
}

export default function AccountProfile({ addresses, zones }: ProfileProps) {
    const { auth } = usePage().props;
    const user = auth.user!;
    const [editing, setEditing] = useState<Address | 'new' | null>(null);

    const profile = useForm({ name: user.name, email: user.email, phone: user.phone ?? '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const saveProfile = (event: FormEvent) => {
        event.preventDefault();
        profile.put(route('account.profile.update'), { preserveScroll: true });
    };

    const savePassword = (event: FormEvent) => {
        event.preventDefault();
        password.put(route('account.password.update'), { preserveScroll: true, onSuccess: () => password.reset() });
    };

    return (
        <StorefrontLayout>
            <Head title="Profile" />
            <AccountTabs />

            <div className="grid gap-5 lg:grid-cols-2">
                <Card>
                    <CardHeader icon={<UserRound className="size-4" />} title="Your details" />
                    <form onSubmit={saveProfile} className="space-y-4 p-4">
                        <Input label="Full name" required value={profile.data.name} onChange={(event) => profile.setData('name', event.target.value)} error={profile.errors.name} />
                        <Input label="Email" type="email" required value={profile.data.email} onChange={(event) => profile.setData('email', event.target.value)} error={profile.errors.email} />
                        <Input label="Mobile number" type="tel" required value={profile.data.phone} onChange={(event) => profile.setData('phone', event.target.value)} error={profile.errors.phone} />
                        <Button type="submit" loading={profile.processing}>
                            Save details
                        </Button>
                    </form>
                </Card>

                <Card>
                    <CardHeader icon={<KeyRound className="size-4" />} title="Change password" />
                    <form onSubmit={savePassword} className="space-y-4 p-4">
                        <Input label="Current password" type="password" autoComplete="current-password" required value={password.data.current_password} onChange={(event) => password.setData('current_password', event.target.value)} error={password.errors.current_password} />
                        <Input label="New password" type="password" autoComplete="new-password" required value={password.data.password} onChange={(event) => password.setData('password', event.target.value)} error={password.errors.password} />
                        <Input label="Confirm new password" type="password" autoComplete="new-password" required value={password.data.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} error={password.errors.password_confirmation} />
                        <Button type="submit" loading={password.processing}>
                            Update password
                        </Button>
                    </form>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader
                        icon={<MapPin className="size-4" />}
                        title="Delivery addresses"
                        action={
                            <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                                Add address
                            </Button>
                        }
                    />
                    {addresses.length === 0 ? (
                        <EmptyState icon={<MapPin className="size-8" />} title="No saved addresses">
                            Save an address to fill in delivery details with one tap at checkout.
                        </EmptyState>
                    ) : (
                        <ul className="grid gap-px bg-ink-700 sm:grid-cols-2">
                            {addresses.map((address) => (
                                <li key={address.id} className="flex items-start justify-between gap-3 bg-ink-850 p-4">
                                    <div className="min-w-0 text-sm">
                                        <p className="flex items-center gap-2 font-bold text-white">
                                            {address.label} {address.is_default && <Badge tone="green">Default</Badge>}
                                        </p>
                                        <p className="text-ink-300">
                                            {address.recipient_name} · +{address.phone}
                                        </p>
                                        <p className="text-xs text-ink-400">{[address.line1, address.line2, `${address.postcode} ${address.city}`].filter(Boolean).join(', ')}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        <Button variant="ghost" size="sm" onClick={() => setEditing(address)} aria-label={`Edit ${address.label}`}>
                                            <Pencil className="size-4" />
                                        </Button>
                                        <ConfirmButton
                                            variant="ghost"
                                            size="sm"
                                            title="Remove this address?"
                                            message={`${address.label} — ${address.line1}`}
                                            confirmLabel="Remove"
                                            onConfirm={() => router.delete(route('account.addresses.destroy', address.id), { preserveScroll: true })}
                                        >
                                            <Trash2 className="size-4" aria-label={`Remove ${address.label}`} />
                                        </ConfirmButton>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            {editing && <AddressModal key={editing === 'new' ? 'new' : editing.id} address={editing === 'new' ? null : editing} zones={zones} defaults={{ name: user.name, phone: user.phone ?? '' }} onClose={() => setEditing(null)} />}
        </StorefrontLayout>
    );
}

interface AddressModalProps {
    address: Address | null;
    zones: { id: number; name: string }[];
    defaults: { name: string; phone: string };
    onClose: () => void;
}

function AddressModal({ address, zones, defaults, onClose }: AddressModalProps) {
    const form = useForm({
        label: address?.label ?? 'Home',
        recipient_name: address?.recipient_name ?? defaults.name,
        phone: address?.phone ?? defaults.phone,
        line1: address?.line1 ?? '',
        line2: address?.line2 ?? '',
        city: address?.city ?? '',
        postcode: address?.postcode ?? '',
        delivery_zone_id: address?.delivery_zone_id ?? ('' as number | ''),
        notes: address?.notes ?? '',
        is_default: address?.is_default ?? false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (address) {
            form.put(route('account.addresses.update', address.id), options);
        } else {
            form.post(route('account.addresses.store'), options);
        }
    };

    return (
        <Modal
            open
            onClose={onClose}
            title={address ? 'Edit address' : 'Add address'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="address-form" loading={form.processing}>
                        Save address
                    </Button>
                </>
            }
        >
            <form id="address-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                <Input label="Label" placeholder="Home, Office..." required value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} error={form.errors.label} />
                <Select label="Delivery area" value={form.data.delivery_zone_id} onChange={(event) => form.setData('delivery_zone_id', event.target.value ? Number(event.target.value) : '')} error={form.errors.delivery_zone_id}>
                    <option value="">Not sure</option>
                    {zones.map((zone) => (
                        <option key={zone.id} value={zone.id}>
                            {zone.name}
                        </option>
                    ))}
                </Select>
                <Input label="Recipient name" required value={form.data.recipient_name} onChange={(event) => form.setData('recipient_name', event.target.value)} error={form.errors.recipient_name} />
                <Input label="Recipient mobile" type="tel" required value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} error={form.errors.phone} />
                <Input label="Address" required wrapperClassName="sm:col-span-2" value={form.data.line1} onChange={(event) => form.setData('line1', event.target.value)} error={form.errors.line1} />
                <Input label="Building, floor, landmark" wrapperClassName="sm:col-span-2" value={form.data.line2} onChange={(event) => form.setData('line2', event.target.value)} error={form.errors.line2} />
                <Input label="City" required value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} error={form.errors.city} />
                <Input label="Postcode" inputMode="numeric" maxLength={5} required value={form.data.postcode} onChange={(event) => form.setData('postcode', event.target.value)} error={form.errors.postcode} />
                <Input label="Note for the rider" wrapperClassName="sm:col-span-2" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} error={form.errors.notes} />
                <Checkbox label="Use as my default address" className="sm:col-span-2" checked={form.data.is_default} onChange={(event) => form.setData('is_default', event.target.checked)} />
            </form>
        </Modal>
    );
}
