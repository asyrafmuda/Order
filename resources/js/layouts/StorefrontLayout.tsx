import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Clock, LogOut, MapPin, Phone, ReceiptText, ShieldCheck, ShoppingBag, User, UserRound } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Logo } from '@/components/Logo';
import { Flash } from '@/components/ui/Flash';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';

interface StorefrontLayoutProps {
    children: ReactNode;
    /** Search box shown in the header (the menu page provides one). */
    search?: ReactNode;
    /** Full-width bar rendered directly under the header, e.g. order mode or order status. */
    subheader?: ReactNode;
}

const tabs = [
    { label: 'Menu & Ordering', route: 'menu.index', match: ['menu.*', 'checkout.*'] },
    { label: 'Order Tracking & Digital Ticket', route: 'orders.track', match: ['orders.*', 'payments.*'] },
    { label: 'Kitchen & POS Status', route: 'status.index', match: ['status.*'] },
];

export default function StorefrontLayout({ children, search, subheader }: StorefrontLayoutProps) {
    const { shop, kitchen, cart } = usePage().props;
    const delivery = cart?.type === 'delivery';
    const eta = delivery && cart?.zone ? `${cart.zone.eta_minutes - 10}–${cart.zone.eta_minutes} min` : `${kitchen.eta_min}–${kitchen.eta_max} min`;

    return (
        <div className="flex min-h-dvh flex-col">
            <header className="no-print sticky top-0 z-40 border-b border-ink-700 bg-ink-900/95 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
                    <Link href={route('menu.index')} aria-label={`${shop.name} home`}>
                        <Logo />
                    </Link>

                    <div className="hidden items-center gap-2 rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-1.5 sm:flex">
                        <span className="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-extrabold tracking-wide text-white uppercase">
                            {delivery ? 'Delivery' : 'Pickup'}
                        </span>
                        <span className="flex items-center gap-1 text-xs font-semibold whitespace-nowrap text-ink-200">
                            <Clock className="size-3.5 text-flame-400" aria-hidden />
                            {eta}
                        </span>
                    </div>

                    <nav className="mx-2 hidden items-center gap-1 lg:flex" aria-label="Main">
                        {tabs.map((tab) => (
                            <NavTab key={tab.route} {...tab} />
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        {search && <div className="hidden w-56 md:block">{search}</div>}

                        <Link
                            href={cart && cart.count > 0 ? route('checkout.create') : route('menu.index')}
                            className="relative flex h-10 items-center gap-2 rounded-lg border border-ink-600 bg-ink-850 px-3 text-sm font-bold whitespace-nowrap text-flame-400 hover:border-flame-400"
                            aria-label={`Your ticket, ${cart?.count ?? 0} items`}
                        >
                            <ShoppingBag className="size-4" aria-hidden />
                            {money(cart?.total ?? 0)}
                            {cart && cart.count > 0 && (
                                <span className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-extrabold text-white">
                                    {cart.count}
                                </span>
                            )}
                        </Link>

                        <UserMenu />
                    </div>
                </div>

                <nav className="scrollbar-none flex gap-1 overflow-x-auto border-t border-ink-800 px-4 py-2 lg:hidden" aria-label="Main">
                    {tabs.map((tab) => (
                        <NavTab key={tab.route} {...tab} />
                    ))}
                </nav>

                {search && <div className="border-t border-ink-800 px-4 py-2 md:hidden">{search}</div>}
            </header>

            {!shop.is_open && (
                <div className="no-print border-b border-flame-500/30 bg-flame-900 px-4 py-2 text-center text-sm font-semibold text-flame-300">
                    {shop.closed_message}
                </div>
            )}

            {subheader}

            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6">{children}</main>

            <footer className="no-print border-t border-ink-700 bg-ink-900">
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-3">
                        <Logo />
                        <p className="text-xs leading-relaxed text-ink-400">
                            Sizzling smash patties, melted cheddar and bold midnight street grill vibes, crafted fresh daily.
                        </p>
                        <p className="flex items-center gap-1.5 text-xs font-semibold text-go-400">
                            <ShieldCheck className="size-4" aria-hidden /> 100% Halal kitchen
                        </p>
                    </div>
                    <FooterBlock title="Operating Hours">
                        <p>Monday – Sunday</p>
                        <p className="font-bold text-flame-400">
                            {shop.hours.open} – {shop.hours.close}
                        </p>
                        <p>Last order at {shop.hours.last_order}</p>
                    </FooterBlock>
                    <FooterBlock title="Store Location">
                        <p className="flex items-start gap-1.5">
                            <MapPin className="mt-0.5 size-3.5 shrink-0 text-brand-400" aria-hidden />
                            {shop.address}
                        </p>
                        <p>Dine-in, self pickup &amp; delivery hub</p>
                        <a href={shop.maps_url} target="_blank" rel="noreferrer" className="font-semibold text-flame-400 hover:underline">
                            Get directions
                        </a>
                    </FooterBlock>
                    <FooterBlock title="Orders & Contact">
                        <p className="flex items-center gap-1.5">
                            <Phone className="size-3.5 text-brand-400" aria-hidden />
                            {shop.phone}
                        </p>
                        <a href={`https://wa.me/${shop.whatsapp}`} target="_blank" rel="noreferrer" className="font-semibold text-go-400 hover:underline">
                            WhatsApp us
                        </a>
                        <Link href={route('orders.track')} className="block hover:text-white">
                            Track an order
                        </Link>
                    </FooterBlock>
                </div>
                <div className="border-t border-ink-800 px-4 py-4 text-center text-xs text-ink-500">
                    © {new Date().getFullYear()} {shop.name}. All rights reserved.
                </div>
            </footer>

            <Flash />
        </div>
    );
}

function NavTab({ label, route: name, match }: { label: string; route: string; match: string[] }) {
    const active = match.some((pattern) => route().current(pattern));

    return (
        <Link
            href={route(name)}
            className={cn(
                'rounded-lg px-3 py-2 text-xs font-bold whitespace-nowrap transition-colors',
                active ? 'bg-brand-600 text-white' : 'text-ink-300 hover:bg-ink-800 hover:text-white',
            )}
            aria-current={active ? 'page' : undefined}
        >
            {label}
        </Link>
    );
}

function FooterBlock({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="space-y-1.5 text-xs text-ink-400">
            <h3 className="mb-2 text-[11px] font-extrabold tracking-wider text-white uppercase">{title}</h3>
            {children}
        </div>
    );
}

function UserMenu() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const close = (event: MouseEvent) => !ref.current?.contains(event.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    if (!auth.user) {
        return (
            <Link
                href={route('login')}
                className="flex size-10 items-center justify-center rounded-full bg-ink-700 text-ink-200 hover:bg-ink-600"
                aria-label="Sign in"
            >
                <UserRound className="size-5" />
            </Link>
        );
    }

    const item = 'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-ink-200 hover:bg-ink-700';

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex h-10 items-center gap-1 rounded-full bg-brand-900 pr-2 pl-1 ring-1 ring-brand-600/50"
                aria-haspopup="menu"
                aria-expanded={open}
            >
                <span className="flex size-8 items-center justify-center rounded-full bg-brand-600 text-sm font-extrabold text-white">
                    {auth.user.name.charAt(0).toUpperCase()}
                </span>
                <ChevronDown className="size-4 text-ink-300" aria-hidden />
            </button>
            {open && (
                <div role="menu" className="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-ink-700 bg-ink-800 py-1 shadow-2xl">
                    <div className="border-b border-ink-700 px-3 py-2">
                        <p className="truncate text-sm font-bold text-white">{auth.user.name}</p>
                        <p className="truncate text-xs text-ink-400">{auth.user.email}</p>
                    </div>
                    {auth.user.is_staff && (
                        <Link href={auth.user.home_url} className={cn(item, 'font-semibold text-flame-400')} role="menuitem">
                            <ShieldCheck className="size-4" /> {auth.user.role_label} workspace
                        </Link>
                    )}
                    <Link href={route('account.orders')} className={item} role="menuitem">
                        <ReceiptText className="size-4" /> My orders
                    </Link>
                    <Link href={route('account.profile.edit')} className={item} role="menuitem">
                        <User className="size-4" /> Profile &amp; addresses
                    </Link>
                    <button type="button" onClick={() => router.post(route('logout'))} className={item} role="menuitem">
                        <LogOut className="size-4" /> Sign out
                    </button>
                </div>
            )}
        </div>
    );
}
