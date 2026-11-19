import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Search, ShoppingBag } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CustomiseModal } from '@/components/storefront/CustomiseModal';
import { OrderModeBar } from '@/components/storefront/OrderModeBar';
import { ProductCard } from '@/components/storefront/ProductCard';
import { TicketPanel } from '@/components/storefront/TicketPanel';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/Card';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { cartActions } from '@/lib/cart';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { CartZone, Category, ComboOffer, Product } from '@/types';

interface MenuProps {
    categories: Category[];
    combo: ComboOffer | null;
    zones: (CartZone & { areas: string | null })[];
}

export default function MenuIndex({ categories, combo, zones }: MenuProps) {
    const { cart, shop } = usePage().props;
    const [query, setQuery] = useState('');
    const [activeCategory, setActiveCategory] = useState<number | null>(null);
    const [customising, setCustomising] = useState<{ product: Product; withCombo: boolean } | null>(null);

    const needsChoices = (product: Product) =>
        (product.variants?.length ?? 0) > 0 || (product.modifier_groups?.length ?? 0) > 0 || (product.is_combo_eligible && combo !== null);

    const visible = useMemo(() => {
        const term = query.trim().toLowerCase();

        return categories
            .filter((category) => activeCategory === null || category.id === activeCategory)
            .map((category) => ({
                ...category,
                products: (category.products ?? []).filter(
                    (product) => term === '' || `${product.name} ${product.description ?? ''} ${product.code ?? ''}`.toLowerCase().includes(term),
                ),
            }))
            .filter((category) => category.products.length > 0);
    }, [categories, query, activeCategory]);

    // "Add with Burger": upgrade a burger already on the ticket, otherwise start customising the first eligible one.
    const applyHotDeal = () => {
        const eligibleIds = categories.flatMap((category) => category.products ?? []).filter((product) => product.is_combo_eligible);
        const line = cart?.lines.find((item) => !item.combo && eligibleIds.some((product) => product.id === item.product_id));

        if (line) {
            cartActions.upgradeToCombo(line.id, combo?.choices[0]);
        } else if (eligibleIds.find((product) => product.is_orderable)) {
            setCustomising({ product: eligibleIds.find((product) => product.is_orderable)!, withCombo: true });
        }
    };

    const search = (
        <label className="relative block">
            <span className="sr-only">Search the menu</span>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
            <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search burgers, sauces..."
                className="h-10 w-full rounded-lg border border-ink-600 bg-ink-850 pr-3 pl-9 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
            />
        </label>
    );

    const pill = (active: boolean) =>
        cn(
            'flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3.5 text-xs font-bold whitespace-nowrap transition-colors',
            active ? 'bg-brand-600 text-white' : 'border border-ink-700 bg-ink-850 text-ink-300 hover:border-ink-500 hover:text-white',
        );

    return (
        <StorefrontLayout search={search} subheader={<OrderModeBar zones={zones} />}>
            <Head title="Menu & Ordering" />

            {combo && (
                <div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-ink-700 bg-ink-850 px-4 py-3">
                    <Badge tone="solid-flame">Hot deal</Badge>
                    <p className="min-w-0 flex-1 text-sm">
                        <strong className="font-extrabold text-white uppercase">Upgrade to {combo.name}</strong>
                        <span className="text-ink-300"> — {combo.description}</span>
                    </p>
                    <span className="text-sm font-extrabold text-flame-400">+{money(combo.price)}</span>
                    <Button size="sm" onClick={applyHotDeal} disabled={!shop.is_open}>
                        Add with Burger <ArrowRight className="size-4" aria-hidden />
                    </Button>
                </div>
            )}

            <nav className="scrollbar-none sticky top-[7.25rem] z-30 -mx-4 mb-6 flex gap-2 overflow-x-auto bg-ink-950/95 px-4 py-2 backdrop-blur lg:top-[4.25rem]" aria-label="Menu categories">
                <button type="button" className={pill(activeCategory === null)} onClick={() => setActiveCategory(null)} aria-pressed={activeCategory === null}>
                    <span aria-hidden>🔥</span> All Menus
                </button>
                {categories.map((category) => (
                    <button key={category.id} type="button" className={pill(activeCategory === category.id)} onClick={() => setActiveCategory(category.id)} aria-pressed={activeCategory === category.id}>
                        <span aria-hidden>{category.icon}</span> {category.name}
                    </button>
                ))}
            </nav>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-10">
                    {visible.length === 0 && (
                        <EmptyState icon={<Search className="size-8" />} title="Nothing matches that search">
                            Try a different word, or clear the search to see the whole menu.
                        </EmptyState>
                    )}

                    {visible.map((category) => (
                        <section key={category.id} aria-labelledby={`category-${category.id}`}>
                            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    {category.eyebrow && (
                                        <p className="flex items-center gap-1.5 text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">
                                            <span className="size-1.5 rounded-full bg-brand-500" aria-hidden />
                                            {category.eyebrow}
                                        </p>
                                    )}
                                    <h2 id={`category-${category.id}`} className="font-display text-4xl leading-none text-white uppercase">
                                        {category.name}
                                    </h2>
                                </div>
                                {category.tagline && <p className="text-xs text-ink-400">{category.tagline}</p>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {category.products.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        icon={category.icon}
                                        customisable={needsChoices(product)}
                                        disabled={!shop.is_open}
                                        onAdd={() => cartActions.add({ product_id: product.id })}
                                        onCustomise={() => setCustomising({ product, withCombo: false })}
                                    />
                                ))}
                            </div>
                        </section>
                    ))}
                </div>

                <aside className="lg:sticky lg:top-32 lg:self-start">
                    <TicketPanel />
                </aside>
            </div>

            {cart && cart.count > 0 && (
                <Link
                    href={route('checkout.create')}
                    className="no-print fixed inset-x-4 bottom-4 z-30 flex h-12 items-center justify-between rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-glow lg:hidden"
                >
                    <span className="flex items-center gap-2">
                        <ShoppingBag className="size-4" aria-hidden />
                        View ticket · {cart.count} item{cart.count === 1 ? '' : 's'}
                    </span>
                    {money(cart.total)}
                </Link>
            )}

            {customising && (
                <CustomiseModal
                    key={customising.product.id}
                    product={customising.product}
                    combo={combo}
                    preselectCombo={customising.withCombo}
                    onClose={() => setCustomising(null)}
                />
            )}
        </StorefrontLayout>
    );
}
