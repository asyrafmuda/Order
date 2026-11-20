import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Tags } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { RowActions } from '@/components/admin/RowActions';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import type { Category } from '@/types';

interface CategoriesProps {
    categories: Category[];
    stations: { id: number; name: string }[];
}

export default function CategoriesIndex({ categories, stations }: CategoriesProps) {
    const [editing, setEditing] = useState<Category | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Categories"
            description="Sections of the menu, in the order customers see them"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New category
                </Button>
            }
        >
            <Head title="Categories" />

            <Card>
                {categories.length === 0 ? (
                    <EmptyState icon={<Tags className="size-8" />} title="No categories yet">
                        Create a category, then add products to it.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Order</Th>
                                <Th>Category</Th>
                                <Th>Kitchen station</Th>
                                <Th>Products</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((category) => (
                                <tr key={category.id} className="hover:bg-ink-800/60">
                                    <Td className="w-16 text-ink-400">{category.sort_order}</Td>
                                    <Td>
                                        <span className="font-bold text-strong">
                                            <span className="mr-2" aria-hidden>
                                                {category.icon}
                                            </span>
                                            {category.name}
                                        </span>
                                        {category.eyebrow && <span className="block text-xs text-ink-400">{category.eyebrow}</span>}
                                    </Td>
                                    <Td>{stations.find((station) => station.id === category.kitchen_station_id)?.name ?? <span className="text-ink-500">Not set</span>}</Td>
                                    <Td>{category.products_count ?? 0}</Td>
                                    <Td>
                                        <ActiveBadge active={category.is_active} on="Visible" off="Hidden" />
                                    </Td>
                                    <Td>
                                        <RowActions
                                            name={category.name}
                                            onEdit={() => setEditing(category)}
                                            onDelete={() => router.delete(route('admin.categories.destroy', category.id), { preserveScroll: true })}
                                            deleteNote="Only empty categories can be deleted. To take a category off the menu, hide it instead."
                                        />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            {editing && (
                <CategoryForm
                    key={editing === 'new' ? 'new' : editing.id}
                    category={editing === 'new' ? null : editing}
                    stations={stations}
                    nextSort={categories.length}
                    onClose={() => setEditing(null)}
                />
            )}
        </BackOfficeLayout>
    );
}

interface CategoryFormProps {
    category: Category | null;
    stations: { id: number; name: string }[];
    nextSort: number;
    onClose: () => void;
}

function CategoryForm({ category, stations, nextSort, onClose }: CategoryFormProps) {
    const form = useForm({
        name: category?.name ?? '',
        icon: category?.icon ?? '',
        eyebrow: category?.eyebrow ?? '',
        tagline: category?.tagline ?? '',
        kitchen_station_id: category?.kitchen_station_id ? String(category.kitchen_station_id) : '',
        sort_order: String(category?.sort_order ?? nextSort),
        is_active: category?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (category) {
            form.put(route('admin.categories.update', category.id), options);
        } else {
            form.post(route('admin.categories.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={category ? `Edit ${category.name}` : 'New category'}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-[5rem_minmax(0,1fr)] gap-3">
                    <Input label="Icon" placeholder="🍜" maxLength={16} value={form.data.icon} onChange={(event) => form.setData('icon', event.target.value)} error={form.errors.icon} />
                    <Input label="Name" required autoFocus value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                </div>
                <Input
                    label="Eyebrow"
                    placeholder="Chef's favourites"
                    hint="Small line above the section title."
                    value={form.data.eyebrow}
                    onChange={(event) => form.setData('eyebrow', event.target.value)}
                    error={form.errors.eyebrow}
                />
                <Input label="Tagline" placeholder="A short line shown beside the category title" value={form.data.tagline} onChange={(event) => form.setData('tagline', event.target.value)} error={form.errors.tagline} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label="Kitchen station"
                        hint="Where items in this category are cooked."
                        value={form.data.kitchen_station_id}
                        onChange={(event) => form.setData('kitchen_station_id', event.target.value)}
                        error={form.errors.kitchen_station_id}
                    >
                        <option value="">Not set</option>
                        {stations.map((station) => (
                            <option key={station.id} value={station.id}>
                                {station.name}
                            </option>
                        ))}
                    </Select>
                    <Input label="Sort order" type="number" min={0} required value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} error={form.errors.sort_order} />
                </div>
                <Toggle checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} label="Visible on the menu" description="Hidden categories and their products cannot be ordered." />
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {category ? 'Save changes' : 'Create category'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
