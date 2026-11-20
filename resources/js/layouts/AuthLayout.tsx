import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/Logo';
import { Flash } from '@/components/ui/Flash';

interface AuthLayoutProps {
    title: string;
    subtitle?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
}

export default function AuthLayout({ title, subtitle, children, footer }: AuthLayoutProps) {
    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-[radial-gradient(circle_at_50%_-10%,color-mix(in_srgb,var(--color-brand-500)_22%,transparent),transparent_50%)] px-4 py-10">
            <Link href={route('menu.index')} className="mb-8">
                <Logo />
            </Link>
            <div className="w-full max-w-md rounded-2xl border border-ink-700 bg-ink-850 p-6 shadow-2xl sm:p-8">
                <h1 className="font-display text-3xl text-white uppercase">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-ink-400">{subtitle}</p>}
                <div className="mt-6">{children}</div>
            </div>
            {footer && <div className="mt-6 text-center text-sm text-ink-400">{footer}</div>}
            <Flash />
        </div>
    );
}
