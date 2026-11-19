import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import StorefrontLayout from '@/layouts/StorefrontLayout';

interface LegalPageProps {
    title: string;
    updated: string;
    intro: ReactNode;
    children: ReactNode;
}

export function LegalPage({ title, updated, intro, children }: LegalPageProps) {
    const { shop } = usePage().props;

    return (
        <StorefrontLayout>
            <Head title={title} />
            <article className="mx-auto max-w-3xl">
                <p className="text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">{shop.name}</p>
                <h1 className="font-display text-5xl leading-none text-white uppercase">{title}</h1>
                <p className="mt-2 text-xs text-ink-500">Last updated {updated}</p>
                <p className="mt-6 text-sm leading-relaxed text-ink-200">{intro}</p>
                <div className="mt-8 space-y-8">{children}</div>
                <footer className="mt-10 rounded-xl border border-ink-700 bg-ink-850 p-4 text-sm text-ink-300">
                    Questions? Reach us at <strong className="text-white">{shop.phone}</strong> or{' '}
                    <a href={`https://wa.me/${shop.whatsapp}`} target="_blank" rel="noreferrer" className="font-semibold text-flame-400 hover:underline">
                        WhatsApp
                    </a>
                    , or visit us at {shop.address}.
                </footer>
            </article>
        </StorefrontLayout>
    );
}

export function LegalSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section>
            <h2 className="mb-2 text-base font-bold text-white">{title}</h2>
            <div className="space-y-2 text-sm leading-relaxed text-ink-300 [&_li]:ml-5 [&_li]:list-disc [&_strong]:text-ink-100">{children}</div>
        </section>
    );
}
