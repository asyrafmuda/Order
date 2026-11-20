import { Head, router, usePage } from '@inertiajs/react';
import { RotateCcw, Search, Tag } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { inputToSen, money, plural, senToInput } from '@/lib/format';

type PriceType = 'product' | 'variant' | 'modifier' | 'combo';

interface Priced {
    id: number;
    name: string;
    /** Standard price in sen. */
    price: number;
    /** This outlet's own price in sen, or null when it follows the standard price. */
    outlet_price: number | null;
}

interface ProductRow extends Priced {
    code: string | null;
    variants: Priced[];
}

interface PricesProps {
    categories: { id: number; name: string; icon: string | null; products: ProductRow[] }[];
    modifierGroups: { id: number; name: string; modifiers: Priced[] }[];
    combos: Priced[];
}

/** One line a price can be typed on. */
interface Row {
    key: string;
    type: PriceType;
    id: number;
    /** Shown in bold, e.g. the product name. */
    name: string;
    /** Shown after the name, e.g. the size. */
    detail?: string;
    code?: string | null;
    standard: number;
    own: number | null;
}

interface Section {
    key: string;
    title: string;
    icon?: string | null;
    rows: Row[];
}

const toSections = ({ categories, modifierGroups, combos }: PricesProps): Section[] => [
    ...categories.map((category) => ({
        key: `category-${category.id}`,
        title: category.name,
        icon: category.icon,
        // A product that comes in sizes is priced through its sizes.
        rows: category.products.flatMap((product): Row[] =>
            product.variants.length > 0
                ? product.variants.map((variant) => ({
                      key: `variant:${variant.id}`,
                      type: 'variant',
                      id: variant.id,
                      name: product.name,
                      detail: variant.name,
                      code: product.code,
                      standard: variant.price,
                      own: variant.outlet_price,
                  }))
                : [{ key: `product:${product.id}`, type: 'product', id: product.id, name: product.name, code: product.code, standard: product.price, own: product.outlet_price }],
        ),
    })),
    ...modifierGroups.map((group) => ({
        key: `group-${group.id}`,
        title: `Add-ons · ${group.name}`,
        rows: group.modifiers.map((modifier): Row => ({ key: `modifier:${modifier.id}`, type: 'modifier', id: modifier.id, name: modifier.name, standard: modifier.price, own: modifier.outlet_price })),
    })),
    ...(combos.length > 0
        ? [{ key: 'combos', title: 'Combo upgrade', rows: combos.map((combo): Row => ({ key: `combo:${combo.id}`, type: 'combo', id: combo.id, name: combo.name, standard: combo.price, own: combo.outlet_price })) }]
        : []),
];

export default function OutletPrices(props: PricesProps) {
    // Prices are edited for the outlet picked in the header.
    const outlet = usePage().props.outlet.current;
    // Start over with the saved prices after a save, and when another outlet is picked.
    const [version, setVersion] = useState(0);

    return (
        <BackOfficeLayout title="Outlet Prices" description={`What ${outlet.name} charges, where it differs from the standard price`}>
            <Head title={`Prices · ${outlet.name}`} />
            <PriceForm key={`${outlet.id}:${version}`} {...props} outlet={outlet} onSaved={() => setVersion((current) => current + 1)} />
        </BackOfficeLayout>
    );
}

function PriceForm({ outlet, onSaved, ...menu }: PricesProps & { outlet: { id: number; name: string }; onSaved: () => void }) {
    const sections = useMemo(() => toSections(menu), []); // eslint-disable-line react-hooks/exhaustive-deps
    const rows = useMemo(() => sections.flatMap((section) => section.rows), [sections]);

    const [values, setValues] = useState<Record<string, string>>(() => Object.fromEntries(rows.map((row) => [row.key, senToInput(row.own)])));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [query, setQuery] = useState('');

    const ownCount = rows.filter((row) => values[row.key] !== '' && inputToSen(values[row.key]) !== row.standard).length;
    const dirty = rows.some((row) => values[row.key] !== senToInput(row.own));

    const needle = query.trim().toLowerCase();
    const shown = sections
        .map((section) => ({
            ...section,
            rows: needle ? section.rows.filter((row) => `${row.code ?? ''} ${row.name} ${row.detail ?? ''}`.toLowerCase().includes(needle)) : section.rows,
        }))
        .filter((section) => section.rows.length > 0);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.put(
            route('admin.outlet-prices.update'),
            { prices: rows.map((row) => ({ type: row.type, id: row.id, price: values[row.key] === '' ? null : inputToSen(values[row.key]) })) },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: onSaved,
                // The server answers with "prices.<index>.price"; show each message on its own line.
                onError: (messages) =>
                    setErrors(
                        Object.fromEntries(
                            Object.entries(messages).flatMap(([field, message]) => {
                                const row = rows[Number(field.split('.')[1])];

                                return row ? [[row.key, message]] : [];
                            }),
                        ),
                    ),
            },
        );
    };

    if (rows.length === 0) {
        return (
            <Card>
                <EmptyState icon={<Tag className="size-8" />} title="Nothing on the menu yet">
                    Add products first, then set what each outlet charges.
                </EmptyState>
            </Card>
        );
    }

    return (
        <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5 pb-24">
            <Card className="space-y-3 p-4">
                <p className="text-sm text-ink-200">
                    The menu is shared by every outlet. Type a price only where <span className="font-bold text-white">{outlet.name}</span> charges something different; leave the rest blank and
                    they follow the standard price, also when that changes later.
                </p>
                <div className="flex flex-wrap items-center gap-3">
                    <label className="relative block min-w-0 flex-1 basis-56">
                        <span className="sr-only">Search items</span>
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
                        <input
                            type="search"
                            placeholder="Search by name or code"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="h-10 w-full rounded-lg border border-ink-600 bg-ink-900 pr-3 pl-9 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                        />
                    </label>
                    <p className="text-xs font-semibold whitespace-nowrap text-ink-300">
                        <span className={cn('font-bold', ownCount > 0 ? 'text-flame-400' : 'text-ink-100')}>{ownCount}</span> of {plural(rows.length, 'price')} set for this outlet
                    </p>
                </div>
            </Card>

            {shown.length === 0 && (
                <Card>
                    <EmptyState icon={<Search className="size-8" />} title="No items match your search" />
                </Card>
            )}

            {shown.map((section) => (
                <Card key={section.key}>
                    <CardHeader title={section.title} icon={section.icon ? <span aria-hidden>{section.icon}</span> : undefined} />
                    <ul className="divide-y divide-ink-700">
                        {section.rows.map((row) => {
                            const typed = values[row.key];
                            const own = typed === '' ? null : inputToSen(typed);
                            const difference = own === null ? 0 : own - row.standard;

                            return (
                                <li key={row.key} className="flex items-center gap-3 px-4 py-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm leading-snug text-ink-100">
                                            {row.code && <span className="mr-1.5 text-xs font-bold text-ink-400">{row.code}</span>}
                                            <span className="font-semibold text-white">{row.name}</span>
                                            {row.detail && <span className="text-ink-300"> · {row.detail}</span>}
                                        </p>
                                        <p className="mt-0.5 text-xs text-ink-400">
                                            Standard {money(row.standard)}
                                            {difference !== 0 && (
                                                <span className={cn('ml-2 font-bold', difference > 0 ? 'text-flame-400' : 'text-go-400')}>
                                                    {difference > 0 ? '+' : '−'}
                                                    {money(Math.abs(difference))} here
                                                </span>
                                            )}
                                        </p>
                                        {errors[row.key] && (
                                            <p className="mt-0.5 text-xs font-medium text-brand-400" role="alert">
                                                {errors[row.key]}
                                            </p>
                                        )}
                                    </div>
                                    <MoneyInput
                                        aria-label={`Price of ${row.name}${row.detail ? ` ${row.detail}` : ''} at ${outlet.name}`}
                                        wrapperClassName="w-32 shrink-0"
                                        placeholder={senToInput(row.standard)}
                                        max="10000"
                                        value={typed}
                                        onChange={(value) => setValues((current) => ({ ...current, [row.key]: value }))}
                                        className={cn(difference !== 0 && 'font-bold ring-1 ring-flame-500/70', errors[row.key] && 'ring-1 ring-brand-500')}
                                    />
                                </li>
                            );
                        })}
                    </ul>
                </Card>
            ))}

            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-ink-700 bg-ink-900/95 px-4 py-3 backdrop-blur lg:left-60">
                <div className="mx-auto flex max-w-3xl items-center gap-2">
                    <Button
                        variant="ghost"
                        disabled={ownCount === 0}
                        onClick={() => setValues(Object.fromEntries(rows.map((row) => [row.key, ''])))}
                        icon={<RotateCcw className="size-4" aria-hidden />}
                    >
                        <span className="hidden sm:inline">Use standard prices everywhere</span>
                        <span className="sm:hidden">Clear all</span>
                    </Button>
                    <span className="ml-auto text-xs text-ink-400">{dirty ? 'Unsaved changes' : 'All saved'}</span>
                    <Button type="submit" loading={saving} disabled={!dirty}>
                        Save prices
                    </Button>
                </div>
            </div>
        </form>
    );
}
