import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';

interface ProductImageProps {
    src: string | null | undefined;
    alt: string;
    /** Emoji shown on the placeholder when the product has no photo. */
    icon?: string | null;
    className?: string;
    dimmed?: boolean;
}

/** Product photo, or a smoky-grill placeholder when none has been uploaded. */
export function ProductImage({ src, alt, icon, className, dimmed }: ProductImageProps) {
    const { shop } = usePage().props;

    if (src) {
        return <img src={src} alt={alt} loading="lazy" className={cn('size-full object-cover', dimmed && 'opacity-40 grayscale', className)} />;
    }

    return (
        <div
            role="img"
            aria-label={alt}
            className={cn(
                'flex size-full items-center justify-center bg-[radial-gradient(circle_at_50%_115%,color-mix(in_srgb,var(--color-brand-500)_45%,transparent),transparent_55%),radial-gradient(circle_at_20%_0%,color-mix(in_srgb,var(--color-flame-400)_16%,transparent),transparent_45%)] bg-ink-900',
                dimmed && 'opacity-40 grayscale',
                className,
            )}
        >
            <span className="text-[2.75em] drop-shadow-[0_6px_14px_rgb(0_0_0/0.6)]" aria-hidden>
                {icon ?? shop.icon}
            </span>
        </div>
    );
}
