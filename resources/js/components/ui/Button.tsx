import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'success' | 'danger' | 'flame' | 'outline';
type Size = 'xs' | 'sm' | 'md' | 'lg';

const base =
    'inline-flex items-center justify-center gap-2 rounded-lg font-semibold whitespace-nowrap transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50';

const variants: Record<Variant, string> = {
    primary: 'bg-brand-600 text-white hover:bg-brand-500 active:bg-brand-700',
    secondary: 'bg-ink-700 text-ink-100 hover:bg-ink-600',
    ghost: 'text-ink-300 hover:bg-ink-800 hover:text-white',
    success: 'bg-go-500 text-ink-950 hover:bg-go-400',
    danger: 'border border-brand-600/60 text-brand-400 hover:bg-brand-600 hover:text-white',
    flame: 'bg-flame-400 text-ink-950 hover:bg-flame-300',
    outline: 'border border-ink-600 text-ink-100 hover:border-ink-400 hover:bg-ink-800',
};

const sizes: Record<Size, string> = {
    xs: 'h-7 px-2.5 text-xs',
    sm: 'h-9 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-12 px-6 text-base',
};

export function buttonClass(variant: Variant = 'primary', size: Size = 'md', className?: string): string {
    return cn(base, variants[variant], sizes[size], className);
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    icon?: ReactNode;
}

export function Button({ variant, size, loading, icon, className, children, disabled, type = 'button', ...props }: ButtonProps) {
    return (
        <button type={type} className={buttonClass(variant, size, className)} disabled={disabled || loading} {...props}>
            {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : icon}
            {children}
        </button>
    );
}

interface LinkButtonProps extends Omit<InertiaLinkProps, 'size'> {
    variant?: Variant;
    size?: Size;
    icon?: ReactNode;
}

export function LinkButton({ variant, size, icon, className, children, ...props }: LinkButtonProps) {
    return (
        <Link className={buttonClass(variant, size, className)} {...props}>
            {icon}
            {children}
        </Link>
    );
}
