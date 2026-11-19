import { Link } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import type { Paginated } from '@/types';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return page.total > 0 ? <p className="px-4 py-3 text-xs text-ink-400">{page.total} total</p> : null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3 px-4 py-3" aria-label="Pagination">
            <p className="text-xs text-ink-400">
                Showing {page.from}–{page.to} of {page.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) => {
                    const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›').replace('Previous', '').replace('Next', '').trim();
                    const className = cn(
                        'flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-semibold',
                        link.active ? 'bg-brand-600 text-white' : 'bg-ink-800 text-ink-300',
                        link.url ? 'hover:bg-ink-700' : 'opacity-40',
                    );

                    return link.url ? (
                        <Link key={index} href={link.url} preserveScroll preserveState className={className}>
                            {label}
                        </Link>
                    ) : (
                        <span key={index} className={className}>
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
