import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, UtensilsCrossed } from 'lucide-react';
import { useFilters } from '@/components/admin/useFilters';
import { ProductImage } from '@/components/ProductImage';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Input, Select } from '@/components/ui/Form';
import { Pagination } from '@/components/ui/Pagination';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { Option, Paginated, Product, ProductStatus } from '@/types';

interface ProductsIndexProps {
    products: Paginated<Product>;
    filters: { search: string; category: string; status: string };
    categories: { id: number; name: string }[];
    statuses: Option<ProductStatus>[];
}

const statusColor: Record<ProductStatus, string> = {
    available: 'border-go-500/40 text-go-400',
    sold_out: 'border-brand-600/50 text-brand-400',
    coming_soon: 'border-flame-500/40 text-flame-400',
    hidden: 'border-ink-600 text-ink-400',
};

export default function ProductsIndex({ products, filters, categories, statuses }: ProductsIndexProps) {
    const { values, set, reset, active } = useFilters('admin.products.index', filters);

    const changeStatus = (product: Product, status: string) =>
        router.patch(route('admin.products.status', product.id), { status }, { preserveScroll: true, preserveState: true });

    return (
        <BackOfficeLayout
            title="Products"
            description={`${products.total} menu ${products.total === 1 ? 'item' : 'items'}`}
            actions={
                <LinkButton href={route('admin.products.create')} size="sm" icon={<Plus className="size-4" aria-hidden />}>
                    New product
                </LinkButton>
            }
        >
            <Head title="Products" />

            <Card className="mb-4 grid gap-3 p-4 sm:grid-cols-[minmax(0,2fr)_1fr_1fr_auto] sm:items-end">
                <Input
                    label="Search"
                    type="search"
                    placeholder="Name or code"
                    prefix={<Search className="size-4" aria-hidden />}
                    value={values.search}
                    onChange={(event) => set('search', event.target.value)}
                />
                <Select label="Category" value={values.category} onChange={(event) => set('category', event.target.value)}>
                    <option value="">All categories</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </Select>
                <Select label="Status" value={values.status} onChange={(event) => set('status', event.target.value)}>
                    <option value="">Any status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
                <Button variant="ghost" onClick={reset} disabled={!active}>
                    Clear
                </Button>
            </Card>

            <Card>
                {products.data.length === 0 ? (
                    <EmptyState icon={<UtensilsCrossed className="size-8" />} title="No products found">
                        {active ? 'Try different filters.' : 'Add your first menu item to start selling.'}
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>Product</Th>
                                <Th>Category</Th>
                                <Th>Price</Th>
                                <Th>Flags</Th>
                                <Th>Availability</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product) => (
                                <tr key={product.id} className="hover:bg-ink-800/60">
                                    <Td>
                                        <div className="flex items-center gap-3">
                                            <div className="size-11 shrink-0 overflow-hidden rounded-lg text-[0.6rem]">
                                                <ProductImage src={product.image_url} alt="" icon={product.category?.icon} />
                                            </div>
                                            <div className="min-w-0">
                                                <Link href={route('admin.products.edit', product.id)} className="font-bold text-white hover:text-flame-400">
                                                    {product.name}
                                                </Link>
                                                <span className="block text-xs text-ink-400">
                                                    {product.code ?? 'No code'}
                                                    {product.badge && ` · ${product.badge}`}
                                                </span>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td>{product.category?.name}</Td>
                                    <Td className="whitespace-nowrap">
                                        <span className="font-bold text-flame-400">{money(product.from_price)}</span>
                                        {(product.variants?.length ?? 0) > 0 && <span className="block text-xs text-ink-400">{product.variants?.length} sizes</span>}
                                    </Td>
                                    <Td>
                                        <div className="flex flex-wrap gap-1">
                                            {product.is_combo_eligible && <Badge tone="flame">Combo</Badge>}
                                            {product.is_upsell && <Badge tone="blue">Upsell</Badge>}
                                            {product.pos_linked && <Badge tone="green">POS</Badge>}
                                        </div>
                                    </Td>
                                    <Td>
                                        <label className="sr-only" htmlFor={`status-${product.id}`}>
                                            Availability of {product.name}
                                        </label>
                                        <select
                                            id={`status-${product.id}`}
                                            value={product.status}
                                            onChange={(event) => changeStatus(product, event.target.value)}
                                            className={cn('h-8 rounded-lg border bg-ink-900 px-2 text-xs font-bold focus:outline-none', statusColor[product.status])}
                                        >
                                            {statuses.map((status) => (
                                                <option key={status.value} value={status.value} className="text-ink-100">
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                    </Td>
                                    <Td>
                                        <div className="flex justify-end gap-1.5">
                                            <LinkButton href={route('admin.products.edit', product.id)} variant="secondary" size="xs" icon={<Pencil className="size-3.5" aria-hidden />}>
                                                Edit
                                            </LinkButton>
                                            <ConfirmButton
                                                size="xs"
                                                title={`Remove ${product.name}?`}
                                                message="It disappears from the menu. Past orders keep their copy of the item."
                                                confirmLabel="Remove"
                                                icon={<Trash2 className="size-3.5" aria-hidden />}
                                                onConfirm={() => router.delete(route('admin.products.destroy', product.id), { preserveScroll: true })}
                                            >
                                                Remove
                                            </ConfirmButton>
                                        </div>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
                <Pagination page={products} />
            </Card>
        </BackOfficeLayout>
    );
}
