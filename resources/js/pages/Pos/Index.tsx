import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChefHat, LogOut, Search, Ticket, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Logo } from '@/components/Logo';
import { OptionsPanel } from '@/components/pos/OptionsPanel';
import { PaymentDialog, type PaymentResult } from '@/components/pos/PaymentDialog';
import { PosTicket, type PosTotals } from '@/components/pos/PosTicket';
import { ProductTile } from '@/components/pos/ProductTile';
import { Flash } from '@/components/ui/Flash';
import { useNow } from '@/hooks/useNow';
import { cn } from '@/lib/cn';
import { time } from '@/lib/format';
import { hasOptions, needsChoice, signature, unitPrice, type PosLine, type PosLineInput } from '@/lib/pos';
import type { Category, ComboOffer, PosReceipt, Product } from '@/types';

interface PosProps {
    categories: Category[];
    combo: ComboOffer | null;
    quickNotes: string[];
    tax: { rate: number; inclusive: boolean };
    recent: { uuid: string; order_number: string; customer_name: string; total: number; status_label: string; is_paid: boolean }[];
}

type OrderType = 'pickup' | 'dine_in';

const STORAGE_KEY = 'pos.ticket';

let nextKey = 0;
const newKey = () => `line-${Date.now()}-${nextKey++}`;

export default function PosIndex({ categories, combo, quickNotes, tax, recent }: PosProps) {
    const { auth, flash } = usePage().props;
    const now = useNow(30000);
    const searchRef = useRef<HTMLInputElement>(null);

    const products = useMemo(() => categories.flatMap((category) => (category.products ?? []).map((product) => ({ product, icon: category.icon }))), [categories]);

    const [lines, setLines] = useState<PosLine[]>(() => restore(products.map((entry) => entry.product)).lines);
    const [type, setType] = useState<OrderType>(() => restore([]).type);
    const [customerName, setCustomerName] = useState(() => restore([]).customerName);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<{ product: Product; line: PosLine | null } | null>(null);
    const [paying, setPaying] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [receipt, setReceipt] = useState<PosReceipt | null>(null);

    // A page reload (or a dropped connection) must not lose a half-built order.
    useEffect(() => {
        try {
            window.sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ type, customerName, lines: lines.map(({ product, key: _key, ...line }) => ({ ...line, productId: product.id })) }),
            );
        } catch {
            // Storage unavailable (private mode): the ticket simply is not persisted.
        }
    }, [lines, type, customerName]);

    // The server confirmed the order: show the receipt and start a fresh ticket.
    useEffect(() => {
        if (flash.receipt) {
            setReceipt(flash.receipt);
            setLines([]);
            setCustomerName('');
            setType('pickup');
            setPaying(false);
            setErrors({});
        }
    }, [flash.receipt?.uuid]);

    const totals: PosTotals = useMemo(() => {
        const subtotal = lines.reduce((sum, line) => sum + unitPrice(line, combo) * line.quantity, 0);
        const taxAmount = tax.rate <= 0 ? 0 : Math.round(tax.inclusive ? (subtotal * tax.rate) / (100 + tax.rate) : (subtotal * tax.rate) / 100);

        return {
            count: lines.reduce((sum, line) => sum + line.quantity, 0),
            subtotal,
            tax: taxAmount,
            total: subtotal + (tax.inclusive ? 0 : taxAmount),
        };
    }, [lines, combo, tax]);

    const visible = useMemo(() => {
        const term = query.trim().toLowerCase();

        return products.filter(({ product }) =>
            term !== '' ? `${product.code ?? ''} ${product.name}`.toLowerCase().includes(term) : categoryId === null || product.category_id === categoryId,
        );
    }, [products, query, categoryId]);

    const quantityOf = (productId: number) => lines.filter((line) => line.product.id === productId).reduce((sum, line) => sum + line.quantity, 0);

    const addLine = useCallback((input: PosLineInput) => {
        setReceipt(null);
        setLines((current) => {
            const match = current.find((line) => signature(line) === signature(input));

            return match
                ? current.map((line) => (line.key === match.key ? { ...line, quantity: Math.min(50, line.quantity + input.quantity) } : line))
                : [...current, { ...input, key: newKey() }];
        });
    }, []);

    const quickAdd = (product: Product) => addLine({ product, variantId: null, modifierIds: [], combo: false, comboChoice: null, notes: '', quantity: 1 });

    const open = (product: Product) => (needsChoice(product) ? setOptions({ product, line: null }) : quickAdd(product));

    const submitOptions = (input: PosLineInput) => {
        if (options?.line) {
            const key = options.line.key;
            setLines((current) => current.map((line) => (line.key === key ? { ...input, key } : line)));
        } else {
            addLine(input);
        }

        setOptions(null);
        setQuery('');
    };

    const setQuantity = (key: string, quantity: number) =>
        setLines((current) => (quantity < 1 ? current.filter((line) => line.key !== key) : current.map((line) => (line.key === key ? { ...line, quantity: Math.min(50, quantity) } : line))));

    const charge = (result: PaymentResult) => {
        setProcessing(true);
        router.post(
            route('pos.orders.store'),
            {
                type,
                customer_name: customerName.trim() || null,
                customer_phone: result.phone || null,
                notes: result.notes || null,
                payment_method: result.payment === 'later' ? 'cash' : result.payment,
                paid: result.payment !== 'later',
                tendered: result.tendered,
                lines: lines.map((line) => ({
                    product_id: line.product.id,
                    variant_id: line.variantId,
                    modifier_ids: line.modifierIds,
                    combo: line.combo,
                    combo_choice: line.comboChoice,
                    quantity: line.quantity,
                    notes: line.notes || null,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (failed) => setErrors(failed),
                onFinish: () => setProcessing(false),
            },
        );
    };

    // Keyboard: "/" search, F2 charge, Esc clears the search. Panels handle their own Enter / Esc.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const typing = event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement;

            if (event.key === '/' && !typing && !options && !paying) {
                event.preventDefault();
                searchRef.current?.focus();
            } else if (event.key === 'F2' && lines.length > 0 && !options) {
                event.preventDefault();
                setPaying(true);
            } else if (event.key === 'Escape' && !options && !paying && query !== '') {
                setQuery('');
                searchRef.current?.blur();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [options, paying, lines.length, query]);

    const tab = (active: boolean) =>
        cn(
            'flex shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-bold whitespace-nowrap transition-colors lg:h-14 lg:w-full lg:justify-start h-11',
            active ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 bg-ink-850 text-ink-200 hover:border-ink-500',
        );

    return (
        <div className="flex min-h-dvh flex-col bg-ink-950 md:h-dvh">
            <Head title="Take Order" />

            <header className="flex items-center gap-3 border-b border-ink-700 bg-ink-900 px-3 py-2">
                <Link href={auth.user?.home_url ?? '/'} aria-label="Back to workspace">
                    <Logo compact />
                </Link>
                <h1 className="hidden font-display text-2xl leading-none text-white uppercase sm:block">Take Order</h1>

                <label className="relative mx-auto block w-full max-w-md">
                    <span className="sr-only">Search the menu</span>
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
                    <input
                        ref={searchRef}
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            // Enter picks the first match, so "b5 ⏎" is all it takes.
                            if (event.key === 'Enter' && !options && !paying && visible[0]?.product.is_orderable) {
                                event.preventDefault();
                                // Stop here: the options panel starts listening for Enter as soon as it
                                // opens, and must not receive this same key press.
                                event.stopPropagation();
                                open(visible[0].product);
                                // Hand the keyboard to the options panel (or back to the page) so the next Enter is not typed here.
                                event.currentTarget.blur();
                                if (!needsChoice(visible[0].product)) setQuery('');
                            }
                        }}
                        placeholder="Search by name or code…  ( / )"
                        className="h-11 w-full rounded-xl border border-ink-600 bg-ink-850 pr-9 pl-9 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                    />
                    {query !== '' && (
                        <button type="button" onClick={() => setQuery('')} className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-ink-400 hover:text-white" aria-label="Clear search">
                            <X className="size-4" />
                        </button>
                    )}
                </label>

                <nav className="flex items-center gap-1" aria-label="Workspaces">
                    <span className="mr-1 hidden font-display text-2xl leading-none text-flame-400 tabular-nums lg:block" aria-label="Current time">
                        {time(new Date(now).toISOString())}
                    </span>
                    <Link href={route('counter.index')} className="flex h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-bold text-ink-200 hover:bg-ink-800">
                        <Ticket className="size-4" aria-hidden /> <span className="hidden sm:inline">Counter</span>
                    </Link>
                    <Link href={route('kitchen.index')} className="flex h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-bold text-ink-200 hover:bg-ink-800">
                        <ChefHat className="size-4" aria-hidden /> <span className="hidden sm:inline">Kitchen</span>
                    </Link>
                    <button type="button" onClick={() => router.post(route('logout'))} className="flex h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-bold text-ink-400 hover:bg-ink-800 hover:text-white" aria-label="Sign out">
                        <LogOut className="size-4" aria-hidden />
                    </button>
                </nav>
            </header>

            <div className="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[minmax(0,1fr)_21rem] md:grid-rows-[auto_minmax(0,1fr)] lg:grid-cols-[9rem_minmax(0,1fr)_24rem] lg:grid-rows-[minmax(0,1fr)]">
                <nav
                    className="scrollbar-none flex gap-2 overflow-x-auto border-b border-ink-700 bg-ink-900 p-2 md:col-span-2 lg:col-span-1 lg:flex-col lg:overflow-x-hidden lg:overflow-y-auto lg:border-r lg:border-b-0"
                    aria-label="Menu categories"
                >
                    <button type="button" className={tab(categoryId === null && query === '')} onClick={() => (setCategoryId(null), setQuery(''))} aria-pressed={categoryId === null}>
                        <span aria-hidden>🔥</span> All
                    </button>
                    {categories.map((category) => (
                        <button
                            key={category.id}
                            type="button"
                            className={tab(categoryId === category.id && query === '')}
                            onClick={() => (setCategoryId(category.id), setQuery(''))}
                            aria-pressed={categoryId === category.id}
                        >
                            <span aria-hidden>{category.icon}</span>
                            <span className="lg:whitespace-normal lg:leading-tight">{category.name}</span>
                        </button>
                    ))}
                </nav>

                <main className="relative min-h-[55dvh] md:row-start-2 md:min-h-0 lg:col-start-2 lg:row-start-1">
                    <div className="h-full overflow-y-auto p-3">
                        {visible.length === 0 ? (
                            <p className="py-16 text-center text-sm text-ink-400">Nothing on the menu matches “{query}”.</p>
                        ) : (
                            <div className="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2">
                                {visible.map(({ product, icon }) => (
                                    <ProductTile
                                        key={product.id}
                                        product={product}
                                        icon={icon}
                                        inTicket={quantityOf(product.id)}
                                        customisable={hasOptions(product, combo)}
                                        opensOptions={needsChoice(product)}
                                        onAdd={() => quickAdd(product)}
                                        onCustomise={() => setOptions({ product, line: null })}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {options && (
                        <OptionsPanel
                            key={options.line?.key ?? `new-${options.product.id}`}
                            product={options.product}
                            combo={combo}
                            quickNotes={quickNotes}
                            editing={options.line}
                            onSubmit={submitOptions}
                            onClose={() => setOptions(null)}
                        />
                    )}
                </main>

                <div className="flex min-h-0 flex-col md:row-start-2 lg:col-start-3 lg:row-start-1">
                    <PosTicket
                        lines={lines}
                        combo={combo}
                        totals={totals}
                        tax={tax}
                        type={type}
                        customerName={customerName}
                        receipt={receipt}
                        recent={recent}
                        onType={setType}
                        onCustomerName={setCustomerName}
                        onQuantity={setQuantity}
                        onEdit={(line) => setOptions({ product: line.product, line })}
                        onRemove={(key) => setQuantity(key, 0)}
                        onClear={() => setLines([])}
                        onCharge={() => (setErrors({}), setPaying(true))}
                        onDismissReceipt={() => setReceipt(null)}
                    />
                </div>
            </div>

            {paying && <PaymentDialog total={totals.total} processing={processing} errors={errors} onConfirm={charge} onClose={() => setPaying(false)} />}

            <Flash />
        </div>
    );
}

interface Saved {
    type: OrderType;
    customerName: string;
    lines: PosLine[];
}

/** Rebuild the ticket saved in this browser tab, dropping items that left the menu. */
function restore(products: Product[]): Saved {
    const empty: Saved = { type: 'pickup', customerName: '', lines: [] };

    if (typeof window === 'undefined') return empty;

    try {
        const saved = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) ?? 'null');

        if (!saved) return empty;

        return {
            type: saved.type === 'dine_in' ? 'dine_in' : 'pickup',
            customerName: typeof saved.customerName === 'string' ? saved.customerName : '',
            lines: (Array.isArray(saved.lines) ? saved.lines : []).flatMap((line: Omit<PosLine, 'product' | 'key'> & { productId: number }) => {
                const product = products.find((item) => item.id === line.productId && item.is_orderable);

                return product ? [{ ...line, product, key: newKey() }] : [];
            }),
        };
    } catch {
        return empty;
    }
}
