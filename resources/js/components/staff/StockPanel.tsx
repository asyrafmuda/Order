import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Modal } from '@/components/ui/Modal';
import { cn } from '@/lib/cn';
import type { ProductStatus } from '@/types';

export interface StockCategory {
    name: string;
    icon: string | null;
    products: { id: number; code: string | null; name: string; status: ProductStatus; status_label: string }[];
}

interface StockPanelProps {
    menu: StockCategory[];
    onClose: () => void;
}

/** "86" board: the crew taps an item the moment it runs out, and again when it is back. */
export function StockPanel({ menu, onClose }: StockPanelProps) {
    const [query, setQuery] = useState('');
    const [pending, setPending] = useState<number | null>(null);
    const term = query.trim().toLowerCase();
    const soldOut = menu.flatMap((category) => category.products).filter((product) => product.status === 'sold_out').length;

    const toggle = (id: number, status: ProductStatus) => {
        setPending(id);
        router.patch(
            route('kitchen.products.status', id),
            { status: status === 'sold_out' ? 'available' : 'sold_out' },
            { preserveScroll: true, preserveState: true, only: ['menu', 'flash'], onFinish: () => setPending(null) },
        );
    };

    return (
        <Modal open onClose={onClose} size="lg" title={`Stock · ${soldOut === 0 ? 'everything available' : `${soldOut} sold out`}`}>
            <label className="relative mb-4 block">
                <span className="sr-only">Find an item</span>
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
                <input
                    type="search"
                    autoFocus
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Find an item…"
                    className="h-11 w-full rounded-lg border border-ink-600 bg-ink-900 pr-3 pl-9 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                />
            </label>

            <div className="space-y-5">
                {menu.map((category) => {
                    const products = category.products.filter((product) => term === '' || `${product.code ?? ''} ${product.name}`.toLowerCase().includes(term));

                    if (products.length === 0) return null;

                    return (
                        <section key={category.name}>
                            <h3 className="mb-2 text-xs font-extrabold tracking-[0.12em] text-ink-300 uppercase">
                                <span aria-hidden>{category.icon}</span> {category.name}
                            </h3>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {products.map((product) => {
                                    const locked = product.status !== 'available' && product.status !== 'sold_out';
                                    const out = product.status === 'sold_out';

                                    return (
                                        <button
                                            key={product.id}
                                            type="button"
                                            disabled={locked || pending === product.id}
                                            onClick={() => toggle(product.id, product.status)}
                                            aria-pressed={out}
                                            className={cn(
                                                'flex h-14 items-center justify-between gap-3 rounded-xl border px-3 text-left text-sm font-bold transition-colors disabled:opacity-50',
                                                out ? 'border-brand-600 bg-brand-900 text-white' : 'border-ink-600 bg-ink-900 text-ink-100 hover:border-ink-400',
                                            )}
                                        >
                                            <span className="min-w-0 truncate">
                                                {product.code && <span className="mr-1.5 text-ink-400">{product.code}</span>}
                                                {product.name}
                                            </span>
                                            <span
                                                className={cn(
                                                    'shrink-0 rounded-md px-2 py-1 text-[10px] font-extrabold tracking-wide uppercase',
                                                    out ? 'bg-brand-600 text-white' : locked ? 'bg-ink-700 text-ink-300' : 'bg-go-900 text-go-400',
                                                )}
                                            >
                                                {locked ? product.status_label : out ? 'Sold out' : 'Available'}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>
                    );
                })}
            </div>
            <p className="mt-5 text-xs text-ink-400">Sold out items stay on the menu but cannot be ordered, online or at the counter. Tap again when they are back.</p>
        </Modal>
    );
}
