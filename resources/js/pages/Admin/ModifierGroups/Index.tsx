import { Head, router, useForm } from '@inertiajs/react';
import { Plus, SlidersHorizontal, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { RowActions } from '@/components/admin/RowActions';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { inputToSen, money, senToInput } from '@/lib/format';
import type { ModifierGroup } from '@/types';

export default function ModifierGroupsIndex({ groups }: { groups: ModifierGroup[] }) {
    const [editing, setEditing] = useState<ModifierGroup | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Options & Add-ons"
            description="Groups of choices (sauce, spice level, extras) that you attach to products"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New group
                </Button>
            }
        >
            <Head title="Options & add-ons" />

            <Card>
                {groups.length === 0 ? (
                    <EmptyState icon={<SlidersHorizontal className="size-8" />} title="No option groups yet">
                        Create a group such as “Choice of Sauce”, then tick it on the products that need it.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Group</Th>
                                <Th>Options</Th>
                                <Th>Rule</Th>
                                <Th>Used by</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map((group) => (
                                <tr key={group.id} className="hover:bg-ink-800/60">
                                    <Td className="font-bold text-white">{group.name}</Td>
                                    <Td className="max-w-md whitespace-normal">
                                        <div className="flex flex-wrap gap-1">
                                            {(group.modifiers ?? []).map((modifier) => (
                                                <span
                                                    key={modifier.id}
                                                    className={cn('rounded-md bg-ink-700 px-1.5 py-0.5 text-[11px] font-semibold', modifier.is_available ? 'text-ink-200' : 'text-ink-500 line-through')}
                                                >
                                                    {modifier.name}
                                                    {modifier.price > 0 && <span className="text-flame-400"> +{money(modifier.price, false)}</span>}
                                                </span>
                                            ))}
                                        </div>
                                    </Td>
                                    <Td>
                                        <Badge tone={group.min_select > 0 ? 'flame' : 'neutral'}>{group.min_select > 0 ? 'Required' : 'Optional'}</Badge>
                                        <span className="mt-0.5 block text-xs text-ink-400">
                                            {group.min_select === group.max_select ? `Choose ${group.max_select}` : `Choose ${group.min_select}–${group.max_select}`}
                                        </span>
                                    </Td>
                                    <Td>
                                        {group.products_count ?? 0} product{group.products_count === 1 ? '' : 's'}
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={group.name}
                                            onEdit={() => setEditing(group)}
                                            onDelete={() => router.delete(route('admin.modifier-groups.destroy', group.id), { preserveScroll: true })}
                                            deleteNote={`The group and its options are removed from ${group.products_count ?? 0} product(s). Past orders keep their copy.`}
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && <GroupForm key={editing === 'new' ? 'new' : editing.id} group={editing === 'new' ? null : editing} nextSort={groups.length} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

interface ModifierRow {
    id: number | null;
    name: string;
    price: string;
    is_available: boolean;
}

interface GroupFormData {
    name: string;
    min_select: string;
    max_select: string;
    sort_order: string;
    modifiers: ModifierRow[];
}

function GroupForm({ group, nextSort, onClose }: { group: ModifierGroup | null; nextSort: number; onClose: () => void }) {
    const form = useForm<GroupFormData>({
        name: group?.name ?? '',
        min_select: String(group?.min_select ?? 0),
        max_select: String(group?.max_select ?? 1),
        sort_order: String(group?.sort_order ?? nextSort),
        modifiers: group?.modifiers?.map((modifier) => ({
            id: modifier.id,
            name: modifier.name,
            price: senToInput(modifier.price),
            is_available: modifier.is_available,
        })) ?? [{ id: null, name: '', price: '0.00', is_available: true }],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const { data, setData } = form;

    const setRow = (index: number, changes: Partial<ModifierRow>) =>
        setData(
            'modifiers',
            data.modifiers.map((row, position) => (position === index ? { ...row, ...changes } : row)),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            modifiers: values.modifiers.map((row) => ({ ...row, price: inputToSen(row.price) })),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (group) {
            form.put(route('admin.modifier-groups.update', group.id), options);
        } else {
            form.post(route('admin.modifier-groups.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={group ? `Edit ${group.name}` : 'New option group'} size="lg">
            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_7rem_7rem]">
                    <Input label="Group name" required autoFocus placeholder="Choice of Sauce" value={data.name} onChange={(event) => setData('name', event.target.value)} error={errors.name} />
                    <Input label="Min choices" type="number" min={0} max={20} required value={data.min_select} onChange={(event) => setData('min_select', event.target.value)} error={errors.min_select} />
                    <Input label="Max choices" type="number" min={1} max={20} required value={data.max_select} onChange={(event) => setData('max_select', event.target.value)} error={errors.max_select} />
                    <Input label="Sort order" type="number" min={0} required value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} error={errors.sort_order} />
                </div>
                <p className="-mt-2 text-xs text-ink-400">Min 0 = optional. Min 1 / Max 1 = the customer must pick exactly one.</p>

                <fieldset>
                    <legend className="mb-2 flex w-full items-center justify-between text-xs font-extrabold tracking-wider text-ink-300 uppercase">
                        Options
                        <Button
                            variant="secondary"
                            size="xs"
                            onClick={() => setData('modifiers', [...data.modifiers, { id: null, name: '', price: '0.00', is_available: true }])}
                            icon={<Plus className="size-3.5" aria-hidden />}
                        >
                            Add option
                        </Button>
                    </legend>
                    <div className="space-y-2">
                        {data.modifiers.map((row, index) => (
                            <div key={row.id ?? `new-${index}`} className="grid grid-cols-[minmax(0,1fr)_7.5rem_auto_auto] items-start gap-2">
                                <Input
                                    aria-label={`Option ${index + 1} name`}
                                    placeholder="e.g. Black Pepper"
                                    required
                                    value={row.name}
                                    onChange={(event) => setRow(index, { name: event.target.value })}
                                    error={errors[`modifiers.${index}.name`]}
                                />
                                <MoneyInput aria-label={`Option ${index + 1} extra price`} required value={row.price} onChange={(value) => setRow(index, { price: value })} error={errors[`modifiers.${index}.price`]} />
                                <label className="flex h-10 cursor-pointer items-center gap-1.5 text-xs font-semibold text-ink-300">
                                    <input type="checkbox" className="size-4 accent-go-500" checked={row.is_available} onChange={(event) => setRow(index, { is_available: event.target.checked })} />
                                    Available
                                </label>
                                <Button
                                    variant="ghost"
                                    className="px-2.5"
                                    disabled={data.modifiers.length === 1}
                                    onClick={() => setData('modifiers', data.modifiers.filter((_, position) => position !== index))}
                                    aria-label={`Remove option ${index + 1}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                    {errors.modifiers && <p className="mt-2 text-xs font-medium text-brand-400">{errors.modifiers}</p>}
                </fieldset>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {group ? 'Save changes' : 'Create group'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
