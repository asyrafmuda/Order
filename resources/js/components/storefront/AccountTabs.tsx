import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';

const tabs = [
    { label: 'My orders', route: 'account.orders' },
    { label: 'Profile & addresses', route: 'account.profile.edit' },
];

export function AccountTabs() {
    const { auth } = usePage().props;

    return (
        <div className="mb-6">
            <p className="text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">My account</p>
            <h1 className="font-display text-5xl leading-none text-strong uppercase">Hi, {auth.user?.name.split(' ')[0]}</h1>
            <nav className="mt-4 flex gap-1 border-b border-ink-700" aria-label="Account">
                {tabs.map((tab) => {
                    const active = route().current(tab.route);

                    return (
                        <Link
                            key={tab.route}
                            href={route(tab.route)}
                            aria-current={active ? 'page' : undefined}
                            className={cn('-mb-px border-b-2 px-3 py-2.5 text-sm font-bold', active ? 'border-brand-500 text-strong' : 'border-transparent text-ink-400 hover:text-strong')}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
