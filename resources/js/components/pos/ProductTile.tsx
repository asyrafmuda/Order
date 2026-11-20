import { SlidersHorizontal } from 'lucide-react';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { Product } from '@/types';

interface ProductTileProps {
    product: Product;
    icon: string | null;
    /** How many of this product are already on the ticket. */
    inTicket: number;
    /** Show the corner button that opens options before adding. */
    customisable: boolean;
    /** Tapping the tile opens the options instead of adding straight away. */
    opensOptions: boolean;
    onAdd: () => void;
    onCustomise: () => void;
}

/** A large touch target for one menu item. One tap adds it to the ticket. */
export function ProductTile({ product, icon, inTicket, customisable, opensOptions, onAdd, onCustomise }: ProductTileProps) {
    const available = product.is_orderable;

    return (
        <div className="relative">
            <button
                type="button"
                disabled={!available}
                onClick={opensOptions ? onCustomise : onAdd}
                className={cn(
                    'flex h-[6.5rem] w-full flex-col justify-between rounded-xl border p-2.5 text-left transition-[transform,border-color,background-color] duration-100',
                    available
                        ? 'border-ink-700 bg-ink-850 hover:border-ink-500 active:scale-[0.97] active:border-flame-400 active:bg-ink-800'
                        : 'cursor-not-allowed border-ink-800 bg-ink-900 opacity-50',
                    inTicket > 0 && 'border-brand-600/70 bg-brand-900/40',
                )}
            >
                <span className="flex items-center gap-1.5">
                    {product.code && <span className="rounded bg-flame-400 px-1.5 py-0.5 text-[10px] leading-none font-extrabold text-ink-950">{product.code}</span>}
                    <span className="text-sm leading-none" aria-hidden>
                        {icon}
                    </span>
                    {!available && <span className="text-[10px] font-extrabold tracking-wide text-danger-400 uppercase">{product.status_label}</span>}
                </span>
                <span className="line-clamp-2 pr-7 text-[13px] leading-tight font-bold text-strong">{product.name}</span>
                <span className="flex items-end justify-between">
                    <span className="text-sm font-extrabold text-flame-400">
                        {(product.variants?.length ?? 0) > 1 && <span className="mr-1 text-[10px] font-semibold text-ink-400">from</span>}
                        {money(product.from_price)}
                    </span>
                    {inTicket > 0 && (
                        <span className="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-extrabold text-white" aria-label={`${inTicket} on the ticket`}>
                            {inTicket}
                        </span>
                    )}
                </span>
            </button>

            {available && customisable && !opensOptions && (
                <button
                    type="button"
                    onClick={onCustomise}
                    className="absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-lg bg-ink-700 text-ink-200 hover:bg-ink-600 hover:text-strong"
                    aria-label={`Options for ${product.name}`}
                    title="Options"
                >
                    <SlidersHorizontal className="size-4" />
                </button>
            )}
        </div>
    );
}
