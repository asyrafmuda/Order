import { Head, Link, router, useForm } from '@inertiajs/react';
import { Flame, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { RowActions } from '@/components/admin/RowActions';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Textarea, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { inputToSen, money, senToInput } from '@/lib/format';
import type { ComboOffer } from '@/types';

type Offer = ComboOffer & { is_active: boolean };

export default function ComboOffersIndex({ offers, eligibleProducts }: { offers: Offer[]; eligibleProducts: number }) {
    const [editing, setEditing] = useState<Offer | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Combo Offer"
            description="The “upgrade to combo” hot deal shown on the menu"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New combo
                </Button>
            }
        >
            <Head title="Combo offer" />

            <p className="mb-4 rounded-lg border border-ink-700 bg-ink-850 px-4 py-3 text-sm text-ink-300">
                One combo is offered at a time — activating a combo switches the others off. It can be added to{' '}
                <strong className="text-strong">{eligibleProducts}</strong> product{eligibleProducts === 1 ? '' : 's'} marked “Combo upgrade” in{' '}
                <Link href={route('admin.products.index')} className="font-semibold text-flame-400 hover:underline">
                    Products
                </Link>
                .
            </p>

            <Card>
                {offers.length === 0 ? (
                    <EmptyState icon={<Flame className="size-8" />} title="No combo offer">
                        Create one to show the hot deal banner on the menu.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Combo</Th>
                                <Th>Choices</Th>
                                <Th>Upgrade price</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {offers.map((offer) => (
                                <tr key={offer.id} className="hover:bg-ink-800/60">
                                    <Td>
                                        <span className="font-bold text-strong">{offer.name}</span>
                                        {offer.description && <span className="block text-xs text-ink-400">{offer.description}</span>}
                                    </Td>
                                    <Td className="text-xs text-ink-300">{offer.choices.length > 0 ? offer.choices.join(' / ') : '—'}</Td>
                                    <Td className="font-bold text-flame-400">+{money(offer.price)}</Td>
                                    <Td>
                                        <ActiveBadge active={offer.is_active} on="On the menu" off="Off" />
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={offer.name}
                                            onEdit={() => setEditing(offer)}
                                            onDelete={() => router.delete(route('admin.combo-offers.destroy', offer.id), { preserveScroll: true })}
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && <OfferForm key={editing === 'new' ? 'new' : editing.id} offer={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function OfferForm({ offer, onClose }: { offer: Offer | null; onClose: () => void }) {
    const form = useForm({
        name: offer?.name ?? '',
        description: offer?.description ?? '',
        price: offer ? senToInput(offer.price) : '',
        choices: (offer?.choices ?? []).join('\n'),
        is_active: offer?.is_active ?? true,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            price: inputToSen(data.price),
            choices: data.choices
                .split('\n')
                .map((choice) => choice.trim())
                .filter(Boolean),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (offer) {
            form.put(route('admin.combo-offers.update', offer.id), options);
        } else {
            form.post(route('admin.combo-offers.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={offer ? `Edit ${offer.name}` : 'New combo offer'}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
                    <Input label="Name" required autoFocus placeholder="Combo Set" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={errors.name} />
                    <MoneyInput label="Upgrade price" required value={form.data.price} onChange={(value) => form.setData('price', value)} error={errors.price} />
                </div>
                <Input
                    label="What’s included"
                    placeholder="Seasoned Fries + Iced Soft Drink / Lemon Tea"
                    value={form.data.description}
                    onChange={(event) => form.setData('description', event.target.value)}
                    error={errors.description}
                />
                <Textarea
                    label="Choices"
                    rows={4}
                    placeholder={'Iced Soft Drink\nIced Lemon Tea'}
                    hint="One per line. Customers pick one (e.g. the drink). Leave empty if there is nothing to choose."
                    value={form.data.choices}
                    onChange={(event) => form.setData('choices', event.target.value)}
                    error={errors.choices ?? Object.entries(errors).find(([key]) => key.startsWith('choices.'))?.[1]}
                />
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Offer on the menu" description="Switches any other combo off." />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {offer ? 'Save changes' : 'Create combo'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
