import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';

export function Logo({ className, compact = false }: { className?: string; compact?: boolean }) {
    const { shop } = usePage().props;

    return (
        <span className={cn('flex min-w-0 items-center gap-2.5', className)}>
            {shop.logo_url ? (
                <img src={shop.logo_url} alt="" className="size-9 shrink-0 rounded-lg object-cover shadow-glow" />
            ) : (
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-lg shadow-glow" aria-hidden>
                    {shop.icon}
                </span>
            )}
            {!compact && (
                // A long shop name is cut short with "…" rather than pushing the header off a phone screen.
                <span className="min-w-0 leading-none">
                    <span className="block truncate font-display text-xl text-white uppercase">{shop.name}</span>
                    <span className="block truncate text-[10px] font-bold tracking-[0.2em] text-flame-400 uppercase">{shop.tagline}</span>
                </span>
            )}
        </span>
    );
}
