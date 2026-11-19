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

    return (
        <article
            className={cn(
                'group flex flex-col overflow-hidden rounded-xl border border-ink-700 bg-ink-850 transition-colors',
                product.is_orderable ? 'hover:border-ink-500' : 'opacity-80',
            )}
        >
            <div className="relative aspect-[4/3] overflow-hidden text-4xl">
                <ProductImage src={product.image_url} alt={product.name} icon={icon} dimmed={!product.is_orderable} className="transition-transform duration-300 group-hover:scale-105" />
                {product.code && (
                    <span className="absolute top-2 left-2 rounded-md bg-flame-400 px-1.5 py-0.5 text-[11px] font-extrabold text-ink-950">{product.code}</span>
                )}
                <span className="absolute top-2 right-2">
                    {!product.is_orderable ? (
                        <Badge tone="red">{product.status_label}</Badge>
                    ) : (
                        product.badge && <Badge tone={product.badge.toLowerCase().includes('pick') ? 'solid-red' : 'green'}>{product.badge}</Badge>
                    )}
                </span>
            </div>

            <div className="flex flex-1 flex-col gap-2 p-3.5">
                <h3 className="text-[15px] leading-snug font-bold text-white">{product.name}</h3>
                {product.description && <p className="line-clamp-2 text-xs leading-relaxed text-ink-400">{product.description}</p>}

                {product.tags.length > 0 && (
                    <ul className={cn('gap-1', product.tags.length > 2 ? 'space-y-1' : 'flex flex-wrap')}>
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

                <div className="mt-auto flex items-end justify-between gap-2 pt-2">
                    <div>
                        <p className="text-[10px] font-bold tracking-wider text-ink-400 uppercase">{variants.length > 1 ? 'Starts at' : 'Price'}</p>
                        <p className={cn('text-lg leading-tight font-extrabold', product.is_orderable ? 'text-flame-400' : 'text-ink-500')}>
                            {money(product.from_price)}
                        </p>
                    </div>
                    {!product.is_orderable ? (
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
                    )}
                </div>
            </div>
        </article>
    );
}
