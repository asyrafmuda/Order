import { Plus } from 'lucide-react';
import { ProductImage } from '@/components/ProductImage';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { Product } from '@/types';

interface ProductCardProps {
    product: Product;
    icon: string | null;
    /** True when the product needs choices (variant, options, combo) before it can be added. */
    customisable: boolean;
    disabled: boolean;
    onAdd: () => void;
    onCustomise: () => void;
}

export function ProductCard({ product, icon, customisable, disabled, onAdd, onCustomise }: ProductCardProps) {
    const orderable = product.is_orderable && !disabled;
    const variants = product.variants ?? [];

    const action = !product.is_orderable ? (
        <Button variant="secondary" size="sm" disabled>
            {product.status_label}
        </Button>
    ) : customisable ? (
        <Button size="sm" onClick={onCustomise} disabled={!orderable} icon={<Plus className="size-4" aria-hidden />} aria-label={`Customise ${product.name}`}>
            Customise
        </Button>
    ) : (
        <Button variant="secondary" size="sm" onClick={onAdd} disabled={!orderable} icon={<Plus className="size-4" aria-hidden />} aria-label={`Add ${product.name}`}>
            Add
        </Button>
    );

    return (
        <article
            className={cn(
                // Phones: a compact row (photo left) so a long menu stays scannable. Larger screens: a photo card.
                'group flex overflow-hidden rounded-xl border border-ink-700 bg-ink-850 transition-colors sm:flex-col',
                product.is_orderable ? 'hover:border-ink-500' : 'opacity-80',
            )}
        >
            <div className="relative w-28 shrink-0 self-stretch overflow-hidden text-2xl sm:aspect-[4/3] sm:w-auto sm:self-auto sm:text-4xl">
                <ProductImage src={product.image_url} alt={product.name} icon={icon} dimmed={!product.is_orderable} className="transition-transform duration-300 group-hover:scale-105" />
                {product.code && (
                    <span className="absolute top-1.5 left-1.5 rounded-md bg-flame-400 px-1.5 py-0.5 text-[10px] font-extrabold text-ink-950 sm:top-2 sm:left-2 sm:text-[11px]">{product.code}</span>
                )}
                <span className="absolute right-2 bottom-1.5 left-1.5 sm:top-2 sm:right-2 sm:bottom-auto sm:left-auto">
                    {!product.is_orderable ? (
                        <Badge tone="red">{product.status_label}</Badge>
                    ) : (
                        product.badge && <Badge tone={product.badge.toLowerCase().includes('pick') ? 'solid-red' : 'green'}>{product.badge}</Badge>
                    )}
                </span>
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1.5 p-3 sm:gap-2 sm:p-3.5">
                <h3 className="text-sm leading-snug font-bold text-white sm:text-[15px]">{product.name}</h3>
                {product.description && <p className="line-clamp-2 text-xs leading-relaxed text-ink-400">{product.description}</p>}

                {product.tags.length > 0 && (
                    <ul className={cn('gap-1', product.tags.length > 2 ? 'hidden space-y-1 sm:block' : 'flex flex-wrap')}>
                        {product.tags.map((tag) =>
                            product.tags.length > 2 ? (
                                <li key={tag} className="flex items-start gap-1.5 text-[11px] text-ink-300">
                                    <span className="mt-1 size-1.5 shrink-0 rounded-full bg-go-400" aria-hidden />
                                    {tag}
                                </li>
                            ) : (
                                <li key={tag} className="rounded-md bg-ink-700 px-1.5 py-0.5 text-[10px] font-semibold text-ink-200">
                                    {tag}
                                </li>
                            ),
                        )}
                    </ul>
                )}

                <div className="mt-auto flex items-end justify-between gap-2 pt-1 sm:pt-2">
                    <div>
                        <p className="text-[10px] font-bold tracking-wider text-ink-400 uppercase">{variants.length > 1 ? 'Starts at' : 'Price'}</p>
                        <p className={cn('text-base leading-tight font-extrabold sm:text-lg', product.is_orderable ? 'text-flame-400' : 'text-ink-500')}>{money(product.from_price)}</p>
                    </div>
                    {action}
                </div>
            </div>
        </article>
    );
}
