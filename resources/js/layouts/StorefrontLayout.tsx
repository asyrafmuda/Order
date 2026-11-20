import { Link, router, usePage } from '@inertiajs/react';
import { ChefHat, ChevronDown, Clock, LogOut, MapPin, Phone, ReceiptText, ShieldCheck, ShoppingBag, User, UserRound, UtensilsCrossed, type LucideIcon } from 'lucide-react';
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
    /**
     * Phones only: replaces the bottom tab bar, e.g. with the "Place order"
     * button during checkout so the call to action is always in reach.
     */
    bottomBar?: ReactNode;
}

interface Tab {
    label: string;
    /** Short label for the phone tab bar. */
    short: string;
    icon: LucideIcon;
    route: string;
    match: string[];
}

const tabs: Tab[] = [
    { label: 'Menu & Ordering', short: 'Menu', icon: UtensilsCrossed, route: 'menu.index', match: ['menu.*', 'checkout.*', 'legal.*'] },
    { label: 'Order Tracking & Digital Ticket', short: 'My Order', icon: ReceiptText, route: 'orders.track', match: ['orders.*', 'payments.*'] },
    { label: 'Kitchen & POS Status', short: 'Kitchen', icon: ChefHat, route: 'status.index', match: ['status.*'] },
];

export default function StorefrontLayout({ children, search, subheader, bottomBar }: StorefrontLayoutProps) {
    const { shop, kitchen, cart, auth } = usePage().props;
    const header = useRef<HTMLElement>(null);

    // Sticky elements below the header (category pills) position themselves with --header-h.
    useEffect(() => {
        const element = header.current;

        if (!element) return;

        const update = () => document.documentElement.style.setProperty('--header-h', `${element.offsetHeight}px`);
        const observer = new ResizeObserver(update);
        observer.observe(element);
        update();

        return () => observer.disconnect();
    }, []);

    const delivery = cart?.type === 'delivery';
    const table = cart?.table ?? null;
    const eta = delivery && cart?.zone ? `${cart.zone.eta_minutes - 10}–${cart.zone.eta_minutes} min` : `${kitchen.eta_min}–${kitchen.eta_max} min`;

    return (
        <div className="flex min-h-dvh flex-col">
            <header ref={header} className="no-print sticky top-0 z-40 border-b border-ink-700 bg-ink-900/95 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 lg:py-3">
                    <Link href={route('menu.index')} aria-label={`${shop.name} home`}>
                        <Logo />
                    </Link>

                    <div className="hidden items-center gap-2 rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-1.5 sm:flex">
                        <span className="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-extrabold tracking-wide text-white uppercase">
                            {table ? 'Dine-in' : delivery ? 'Delivery' : 'Pickup'}
                        </span>
                        <span className="flex items-center gap-1 text-xs font-semibold whitespace-nowrap text-ink-200">
                            <Clock className="size-3.5 text-flame-400" aria-hidden />
                            {table ? `${table.name} · ${eta}` : eta}
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
            </header>

            {!shop.is_open && (
                <div className="no-print border-b border-flame-500/30 bg-flame-900 px-4 py-2 text-center text-sm font-semibold text-flame-300">
                    {shop.closed_message}
                </div>
            )}

            {subheader}

            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-4 lg:py-6">{children}</main>

            {/* Room for the fixed bottom bar on phones and tablets. */}
            <footer className="no-print border-t border-ink-700 bg-ink-900 pb-20 lg:pb-0">
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-3">
                        <Logo />
                        {shop.footer_text && <p className="text-xs leading-relaxed text-ink-400">{shop.footer_text}</p>}
                        {shop.badge_text && (
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-go-400">
                                <ShieldCheck className="size-4" aria-hidden /> {shop.badge_text}
                            </p>
                        )}
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
                <div className="flex flex-wrap items-center justify-center gap-x-5 gap-y-1 border-t border-ink-800 px-4 py-4 text-xs text-ink-500">
                    <span>
                        © {new Date().getFullYear()} {shop.name}. All rights reserved.
                    </span>
                    <Link href={route('legal.terms')} className="hover:text-ink-200">
                        Terms of Service
                    </Link>
                    <Link href={route('legal.privacy')} className="hover:text-ink-200">
                        Privacy Notice
                    </Link>
                </div>
            </footer>

            <div className="no-print fixed inset-x-0 bottom-0 z-40 border-t border-ink-700 bg-ink-900/95 pb-safe backdrop-blur lg:hidden">
                {bottomBar ?? (
                    <nav className="mx-auto grid h-16 max-w-xl grid-cols-4" aria-label="Main">
                        {tabs.map((tab) => (
                            <BottomTab key={tab.route} label={tab.short} icon={tab.icon} href={route(tab.route)} active={tab.match.some((pattern) => route().current(pattern))} />
                        ))}
                        <BottomTab
                            label={auth.user ? 'Account' : 'Sign in'}
                            icon={UserRound}
                            href={auth.user ? route('account.orders') : route('login')}
                            active={Boolean(route().current('account.*'))}
                        />
                    </nav>
                )}
            </div>

            <Flash />
        </div>
    );
}

function BottomTab({ label, icon: Icon, href, active }: { label: string; icon: LucideIcon; href: string; active: boolean }) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn('flex flex-col items-center justify-center gap-1 text-[11px] font-bold', active ? 'text-brand-400' : 'text-ink-400 hover:text-white')}
        >
            <Icon className={cn('size-5', active && 'drop-shadow-[0_0_8px_color-mix(in_srgb,var(--color-brand-500)_70%,transparent)]')} aria-hidden />
            {label}
        </Link>
    );
}

function NavTab({ label, route: name, match }: Pick<Tab, 'label' | 'route' | 'match'>) {
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
                        <p className="truncate text-xs text-ink-400">{auth.user.email ?? (auth.user.phone ? `+${auth.user.phone}` : '')}</p>
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
