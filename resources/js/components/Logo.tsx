import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';

export function Logo({ className, compact = false }: { className?: string; compact?: boolean }) {
    const { shop } = usePage().props;

    return (
        <span className={cn('flex items-center gap-2.5', className)}>
            {shop.logo_url ? (
                <img src={shop.logo_url} alt="" className="size-9 shrink-0 rounded-lg object-cover shadow-glow" />
            ) : (
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-lg shadow-glow" aria-hidden>
                    {shop.icon}
                </span>
            )}
            {!compact && (
                <span className="leading-none whitespace-nowrap">
                    <span className="block font-display text-xl text-white uppercase">{shop.name}</span>
                    <span className="block text-[10px] font-bold tracking-[0.2em] text-flame-400 uppercase">{shop.tagline}</span>
                </span>
            )}
        </span>
    );
}
