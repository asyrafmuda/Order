import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { ProductImage } from '@/components/ProductImage';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input, Select, Textarea, Toggle } from '@/components/ui/Form';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { inputToSen, senToInput } from '@/lib/format';
import type { Option, Product, ProductStatus } from '@/types';

interface VariantRow {
    id: number | null;
    name: string;
    price: string;
    is_default: boolean;
}

interface ProductFormProps {
    product: (Product & { modifier_group_ids: number[] }) | null;
    categories: { id: number; name: string; kitchen_station_id: number | null }[];
    stations: { id: number; name: string }[];
    modifierGroups: { id: number; name: string; modifiers_count: number; min_select: number; max_select: number }[];
    statuses: Option<ProductStatus>[];
}

interface FormData {
    category_id: string;
    kitchen_station_id: string;
    code: string;
    name: string;
    description: string;
    price: string;
    status: ProductStatus;
    badge: string;
    tags: string;
    is_combo_eligible: boolean;
    is_upsell: boolean;
    prep_minutes: string;
    sort_order: string;
    image: File | null;
    remove_image: boolean;
    variants: VariantRow[];
    modifier_group_ids: number[];
}

export default function ProductForm({ product, categories, stations, modifierGroups, statuses }: ProductFormProps) {
    const form = useForm<FormData>({
        category_id: product ? String(product.category_id) : String(categories[0]?.id ?? ''),
        kitchen_station_id: product?.kitchen_station_id ? String(product.kitchen_station_id) : '',
        code: product?.code ?? '',
        name: product?.name ?? '',
        description: product?.description ?? '',
        price: product ? senToInput(product.price) : '',
        status: product?.status ?? 'available',
        badge: product?.badge ?? '',
        tags: (product?.tags ?? []).join(', '),
        is_combo_eligible: product?.is_combo_eligible ?? false,
        is_upsell: product?.is_upsell ?? false,
        prep_minutes: String(product?.prep_minutes ?? 10),
        sort_order: String(product?.sort_order ?? 0),
        image: null,
        remove_image: false,
        variants: (product?.variants ?? []).map((variant) => ({
            id: variant.id,
            name: variant.name,
            price: senToInput(variant.price),
            is_default: variant.is_default,
        })),
        modifier_group_ids: product?.modifier_group_ids ?? [],
    });

    // Nested fields (variants.0.name) come back with dotted keys.
    const errors = form.errors as Record<string, string | undefined>;
    const { data, setData } = form;
    const hasVariants = data.variants.length > 0;

    const preview = useMemo(() => (data.image ? URL.createObjectURL(data.image) : null), [data.image]);
    useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

    const categoryStation = stations.find((station) => station.id === categories.find((category) => String(category.id) === data.category_id)?.kitchen_station_id);
    const imageUrl = preview ?? (data.remove_image ? null : (product?.image_url ?? null));

    const setVariant = (index: number, changes: Partial<VariantRow>) =>
        setData(
            'variants',
            data.variants.map((variant, position) => (position === index ? { ...variant, ...changes } : variant)),
        );

    const addVariant = () =>
        setData('variants', [...data.variants, { id: null, name: '', price: data.variants.length === 0 ? data.price : '', is_default: data.variants.length === 0 }]);

    const removeVariant = (index: number) => {
        const remaining = data.variants.filter((_, position) => position !== index);

        if (remaining.length > 0 && !remaining.some((variant) => variant.is_default)) {
            remaining[0] = { ...remaining[0], is_default: true };
        }

        setData('variants', remaining);
    };

    const toggleGroup = (id: number) =>
        setData('modifier_group_ids', data.modifier_group_ids.includes(id) ? data.modifier_group_ids.filter((groupId) => groupId !== id) : [...data.modifier_group_ids, id]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((values) => {
            const variants = values.variants.map((variant) => ({ ...variant, price: inputToSen(variant.price) }));

            return {
                ...values,
                // With sizes the base price follows the cheapest one (the server enforces this too).
                price: variants.length > 0 ? Math.min(...variants.map((variant) => variant.price)) : inputToSen(values.price),
                variants,
                tags: values.tags
                    .split(',')
                    .map((tag) => tag.trim())
                    .filter(Boolean),
                // Files need multipart POST; Laravel reads the real verb from _method.
                ...(product ? { _method: 'put' } : {}),
            };
        });

        form.post(product ? route('admin.products.update', product.id) : route('admin.products.store'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <BackOfficeLayout title={product ? 'Edit product' : 'New product'} description={product?.name ?? 'Add an item to the menu'}>
            <Head title={product ? `Edit ${product.name}` : 'New product'} />

            <Link href={route('admin.products.index')} className="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-ink-300 hover:text-white">
                <ArrowLeft className="size-4" aria-hidden /> All products
            </Link>

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader title="Details" />
                        <div className="grid gap-4 p-4 sm:grid-cols-4">
                            <Input label="Code" placeholder="B1" maxLength={16} value={data.code} onChange={(event) => setData('code', event.target.value)} error={errors.code} />
                            <Input
                                label="Name"
                                required
                                autoFocus={!product}
                                wrapperClassName="sm:col-span-3"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                error={errors.name}
                            />
                            <Textarea
                                label="Description"
                                wrapperClassName="sm:col-span-4"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                error={errors.description}
                            />
                            <Select label="Category" required wrapperClassName="sm:col-span-2" value={data.category_id} onChange={(event) => setData('category_id', event.target.value)} error={errors.category_id}>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </Select>
                            <Select
                                label="Kitchen station"
                                wrapperClassName="sm:col-span-2"
                                hint="Leave on the category’s station unless this item is cooked elsewhere."
                                value={data.kitchen_station_id}
                                onChange={(event) => setData('kitchen_station_id', event.target.value)}
                                error={errors.kitchen_station_id}
                            >
                                <option value="">Same as category{categoryStation ? ` (${categoryStation.name})` : ''}</option>
                                {stations.map((station) => (
                                    <option key={station.id} value={station.id}>
                                        {station.name}
                                    </option>
                                ))}
                            </Select>
                            <Input label="Badge" placeholder="Chef's Pick" maxLength={40} wrapperClassName="sm:col-span-2" value={data.badge} onChange={(event) => setData('badge', event.target.value)} error={errors.badge} />
                            <Input
                                label="Tags"
                                placeholder="150g or 300g, Brioche"
                                hint="Separate with commas."
                                wrapperClassName="sm:col-span-2"
                                value={data.tags}
                                onChange={(event) => setData('tags', event.target.value)}
                                error={errors.tags ?? errors['tags.0']}
                            />
                        </div>
                    </Card>

                    <Card>
                        <CardHeader
                            title="Price & sizes"
                            action={
                                <Button variant="secondary" size="xs" onClick={addVariant} icon={<Plus className="size-3.5" aria-hidden />}>
                                    Add size
                                </Button>
                            }
                        />
                        <div className="space-y-4 p-4">
                            {!hasVariants ? (
                                <MoneyInput label="Price" required wrapperClassName="max-w-48" value={data.price} onChange={(value) => setData('price', value)} error={errors.price} />
                            ) : (
                                <fieldset className="space-y-3">
                                    <legend className="mb-1 text-xs text-ink-400">
                                        Customers pick one size. The menu shows the lowest price as “Starts at”; the selected radio is pre-chosen.
                                    </legend>
                                    {data.variants.map((variant, index) => (
                                        <div key={variant.id ?? `new-${index}`} className="grid grid-cols-[auto_minmax(0,1fr)_8rem_auto] items-start gap-2">
                                            <label className="flex h-10 items-center" title="Default size">
                                                <span className="sr-only">Default size</span>
                                                <input
                                                    type="radio"
                                                    name="default_variant"
                                                    className="size-4 accent-brand-600"
                                                    checked={variant.is_default}
                                                    onChange={() => setData('variants', data.variants.map((row, position) => ({ ...row, is_default: position === index })))}
                                                />
                                            </label>
                                            <Input
                                                aria-label={`Size ${index + 1} name`}
                                                placeholder="e.g. Patty 150g"
                                                required
                                                value={variant.name}
                                                onChange={(event) => setVariant(index, { name: event.target.value })}
                                                error={errors[`variants.${index}.name`]}
                                            />
                                            <MoneyInput
                                                aria-label={`Size ${index + 1} price`}
                                                required
                                                value={variant.price}
                                                onChange={(value) => setVariant(index, { price: value })}
                                                error={errors[`variants.${index}.price`]}
                                            />
                                            <Button variant="ghost" className="px-2.5" onClick={() => removeVariant(index)} aria-label={`Remove size ${index + 1}`}>
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                    {errors.variants && <p className="text-xs font-medium text-brand-400">{errors.variants}</p>}
                                </fieldset>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Options & add-ons" />
                        <div className="p-4">
                            {modifierGroups.length === 0 ? (
                                <p className="text-sm text-ink-400">
                                    No option groups yet.{' '}
                                    <Link href={route('admin.modifier-groups.index')} className="font-semibold text-flame-400 hover:underline">
                                        Create one
                                    </Link>
                                </p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {modifierGroups.map((group) => (
                                        <label key={group.id} className="flex cursor-pointer items-start gap-3 rounded-lg border border-ink-700 bg-ink-900 px-3 py-2.5 hover:border-ink-500">
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 size-4 accent-brand-600"
                                                checked={data.modifier_group_ids.includes(group.id)}
                                                onChange={() => toggleGroup(group.id)}
                                            />
                                            <span>
                                                <span className="block text-sm font-semibold text-ink-100">{group.name}</span>
                                                <span className="block text-xs text-ink-400">
                                                    {group.modifiers_count} options · {group.min_select > 0 ? 'required' : 'optional'}
                                                    {group.max_select > 1 ? `, up to ${group.max_select}` : ''}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                            {errors.modifier_group_ids && <p className="mt-2 text-xs font-medium text-brand-400">{errors.modifier_group_ids}</p>}
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Photo" />
                        <div className="space-y-3 p-4">
                            <div className="aspect-[4/3] overflow-hidden rounded-lg border border-ink-700 text-4xl">
                                <ProductImage src={imageUrl} alt={data.name || 'Product photo'} />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-ink-700 px-3 text-sm font-semibold text-ink-100 hover:bg-ink-600">
                                    <ImagePlus className="size-4" aria-hidden />
                                    {imageUrl ? 'Replace photo' : 'Upload photo'}
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="sr-only"
                                        onChange={(event) => setData((values) => ({ ...values, image: event.target.files?.[0] ?? null, remove_image: false }))}
                                    />
                                </label>
                                {imageUrl && (
                                    <Button variant="ghost" size="sm" onClick={() => setData((values) => ({ ...values, image: null, remove_image: true }))}>
                                        Remove
                                    </Button>
                                )}
                            </div>
                            {errors.image ? <p className="text-xs font-medium text-brand-400">{errors.image}</p> : <p className="text-xs text-ink-400">JPG, PNG or WebP up to 4 MB. Landscape 4:3 looks best.</p>}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Selling" />
                        <div className="space-y-4 p-4">
                            <Select label="Availability" value={data.status} onChange={(event) => setData('status', event.target.value as ProductStatus)} error={errors.status}>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                            <Toggle
                                checked={data.is_combo_eligible}
                                onChange={(checked) => setData('is_combo_eligible', checked)}
                                label="Combo upgrade"
                                description="Offer the active combo set with this item."
                            />
                            <Toggle
                                checked={data.is_upsell}
                                onChange={(checked) => setData('is_upsell', checked)}
                                label="Suggest in ticket"
                                description="Show as a quick add-on in the customer’s ticket (items without sizes only)."
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Input label="Prep (min)" type="number" min={1} max={180} required value={data.prep_minutes} onChange={(event) => setData('prep_minutes', event.target.value)} error={errors.prep_minutes} />
                                <Input label="Sort order" type="number" min={0} required value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} error={errors.sort_order} />
                            </div>
                        </div>
                    </Card>

                    <div className="flex gap-2">
                        <Button type="submit" size="lg" className="flex-1" loading={form.processing}>
                            {product ? 'Save changes' : 'Add product'}
                        </Button>
                        <LinkButton href={route('admin.products.index')} variant="ghost" size="lg">
                            Cancel
                        </LinkButton>
                    </div>
                </div>
            </form>
        </BackOfficeLayout>
    );
}
