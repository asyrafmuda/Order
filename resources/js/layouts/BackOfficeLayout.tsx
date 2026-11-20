import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    Bike,
    ChefHat,
    ClipboardList,
    Flame,
    LayoutDashboard,
    Layers,
    LogOut,
    MapPinned,
    Menu as MenuIcon,
    Plug,
    QrCode,
    Settings,
    SlidersHorizontal,
    Store,
    Tags,
    Ticket,
    Users,
    UtensilsCrossed,
    X,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Logo } from '@/components/Logo';
import { Flash } from '@/components/ui/Flash';
import { cn } from '@/lib/cn';
import type { Role } from '@/types';

interface NavItem {
    label: string;
    route: string;
    match?: string;
    icon: LucideIcon;
    roles: Role[];
}

interface NavSection {
    title: string;
    items: NavItem[];
}

const sections: NavSection[] = [
    {
        title: 'Service',
        items: [
            { label: 'Dashboard', route: 'admin.dashboard', icon: LayoutDashboard, roles: ['admin', 'manager'] },
            { label: 'Take Order (POS)', route: 'pos.index', match: 'pos.*', icon: Zap, roles: ['admin', 'manager', 'cashier', 'waiter'] },
            { label: 'Kitchen Display', route: 'kitchen.index', match: 'kitchen.*', icon: ChefHat, roles: ['admin', 'manager', 'kitchen', 'cashier', 'waiter'] },
            { label: 'Counter', route: 'counter.index', match: 'counter.*', icon: Ticket, roles: ['admin', 'manager', 'cashier', 'waiter'] },
            { label: 'My Deliveries', route: 'rider.index', match: 'rider.*', icon: Bike, roles: ['rider'] },
            { label: 'Orders', route: 'admin.orders.index', match: 'admin.orders.*', icon: ClipboardList, roles: ['admin', 'manager'] },
        ],
    },
    {
        title: 'Menu',
        items: [
            { label: 'Products', route: 'admin.products.index', match: 'admin.products.*', icon: UtensilsCrossed, roles: ['admin'] },
            { label: 'Categories', route: 'admin.categories.index', match: 'admin.categories.*', icon: Tags, roles: ['admin'] },
            { label: 'Options & Add-ons', route: 'admin.modifier-groups.index', match: 'admin.modifier-groups.*', icon: SlidersHorizontal, roles: ['admin'] },
            { label: 'Combo Offer', route: 'admin.combo-offers.index', match: 'admin.combo-offers.*', icon: Flame, roles: ['admin'] },
        ],
    },
    {
        title: 'Operations',
        items: [
            { label: 'Kitchen Stations', route: 'admin.kitchen-stations.index', match: 'admin.kitchen-stations.*', icon: Layers, roles: ['admin'] },
            { label: 'Outlet Details', route: 'admin.outlet.edit', match: 'admin.outlet.*', icon: Store, roles: ['manager'] },
            { label: 'Tables & QR', route: 'admin.tables.index', match: 'admin.tables.*', icon: QrCode, roles: ['admin', 'manager'] },
            { label: 'Delivery Zones', route: 'admin.delivery-zones.index', match: 'admin.delivery-zones.*', icon: MapPinned, roles: ['admin', 'manager'] },
            { label: 'Riders', route: 'admin.riders.index', match: 'admin.riders.*', icon: Bike, roles: ['admin', 'manager'] },
        ],
    },
    {
        title: 'System',
        items: [
            { label: 'Reports', route: 'admin.reports.index', match: 'admin.reports.*', icon: BarChart3, roles: ['admin', 'manager'] },
            { label: 'Outlets', route: 'admin.outlets.index', match: 'admin.outlets.*', icon: Building2, roles: ['admin'] },
            { label: 'Users', route: 'admin.users.index', match: 'admin.users.*', icon: Users, roles: ['admin'] },
            { label: 'Integrations', route: 'admin.integrations.index', match: 'admin.integrations.*', icon: Plug, roles: ['admin'] },
            { label: 'Settings', route: 'admin.settings.edit', match: 'admin.settings.*', icon: Settings, roles: ['admin'] },
        ],
    },
];

interface BackOfficeLayoutProps {
    title: string;
    description?: ReactNode;
    /** Buttons shown on the right of the page header. */
    actions?: ReactNode;
    children: ReactNode;
    /** Remove the max-width so boards (KDS) can use the whole screen. */
    wide?: boolean;
}

export default function BackOfficeLayout({ title, description, actions, children, wide }: BackOfficeLayoutProps) {
    const { auth, kitchen, pos, outlet } = usePage().props;
    const [open, setOpen] = useState(false);
    const role = auth.user?.role ?? 'customer';

    const visible = sections
        .map((section) => ({ ...section, items: section.items.filter((item) => item.roles.includes(role)) }))
        .filter((section) => section.items.length > 0);

    const sidebar = (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between px-4 py-4">
                <Link href={auth.user?.home_url ?? '/'}>
                    <Logo />
                </Link>
                <button type="button" className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-700 lg:hidden" onClick={() => setOpen(false)} aria-label="Close menu">
                    <X className="size-5" />
                </button>
            </div>
            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-4" aria-label="Back office">
                {visible.map((section) => (
                    <div key={section.title}>
                        <p className="mb-1.5 px-2 text-[10px] font-extrabold tracking-[0.15em] text-ink-500 uppercase">{section.title}</p>
                        <ul className="space-y-0.5">
                            {section.items.map((item) => {
                                const active = route().current(item.match ?? item.route);

                                return (
                                    <li key={item.route}>
                                        <Link
                                            href={route(item.route)}
                                            onClick={() => setOpen(false)}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold transition-colors',
                                                active ? 'bg-brand-600 text-white' : 'text-ink-300 hover:bg-ink-800 hover:text-white',
                                            )}
                                            aria-current={active ? 'page' : undefined}
                                        >
                                            <item.icon className="size-4 shrink-0" aria-hidden />
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
            <div className="space-y-1 border-t border-ink-700 p-3">
                <Link href={route('menu.index')} className="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-ink-300 hover:bg-ink-800 hover:text-white">
                    <Store className="size-4" aria-hidden /> View storefront
                </Link>
                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-ink-300 hover:bg-ink-800 hover:text-white"
                >
                    <LogOut className="size-4" aria-hidden /> Sign out
                </button>
                <div className="px-2.5 pt-2">
                    <p className="truncate text-sm font-bold text-white">{auth.user?.name}</p>
                    <p className="text-xs text-ink-400">{auth.user?.role_label}</p>
                </div>
            </div>
        </div>
    );

    return (
        <div className="min-h-dvh lg:pl-60">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-60 border-r border-ink-700 bg-ink-900 lg:block">{sidebar}</aside>

            {open && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/70" onClick={() => setOpen(false)} aria-hidden />
                    <aside className="absolute inset-y-0 left-0 w-64 border-r border-ink-700 bg-ink-900">{sidebar}</aside>
                </div>
            )}

            <header className="sticky top-0 z-30 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-ink-700 bg-ink-900/95 px-4 py-3 backdrop-blur">
                <button type="button" className="rounded-lg p-2 text-ink-300 hover:bg-ink-800 lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu">
                    <MenuIcon className="size-5" />
                </button>
                <div className="min-w-0 flex-1">
                    <h1 className="truncate font-display text-2xl leading-none text-white uppercase">{title}</h1>
                    {description && <p className="mt-0.5 truncate text-xs text-ink-400">{description}</p>}
                </div>
                {/* On phones the outlet sits on its own row under the title, so the title keeps its room. */}
                {outlet.multi &&
                    (outlet.can_switch ? (
                        <label className="order-last flex h-9 basis-full items-center gap-1.5 rounded-lg border border-flame-500/40 bg-flame-900 pr-1 pl-2.5 text-xs font-bold text-flame-300 sm:order-none sm:shrink-0 sm:basis-auto">
                            <Building2 className="size-3.5 shrink-0" aria-hidden />
                            <span className="sr-only">Outlet being viewed</span>
                            <select
                                value={outlet.current.id}
                                onChange={(event) => router.post(route('admin.outlets.switch'), { outlet_id: Number(event.target.value) }, { preserveScroll: true })}
                                className="h-full min-w-0 flex-1 bg-transparent font-bold text-flame-300 focus:outline-none sm:max-w-52 sm:flex-none"
                            >
                                {outlet.options.map((option) => (
                                    <option key={option.id} value={option.id} className="bg-ink-850 text-ink-100">
                                        {option.name}
                                        {option.is_active ? '' : ' (inactive)'}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : (
                        <span className="order-last flex h-8 basis-full items-center gap-1.5 rounded-lg border border-ink-700 bg-ink-850 px-2.5 text-xs font-bold text-ink-100 sm:order-none sm:h-9 sm:shrink-0 sm:basis-auto">
                            <Building2 className="size-3.5 shrink-0 text-flame-400" aria-hidden />
                            <span className="truncate sm:max-w-48">{outlet.current.name}</span>
                        </span>
                    ))}
                <div className="hidden items-center gap-2 xl:flex">
                    <span className="flex items-center gap-1.5 rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-1.5 text-xs font-semibold text-ink-200">
                        <span
                            className={cn('size-2 rounded-full', {
                                'bg-go-400': kitchen.level === 'quiet' || kitchen.level === 'normal',
                                'bg-flame-400': kitchen.level === 'busy',
                                'bg-brand-500': kitchen.level === 'slammed',
                            })}
                        />
                        Kitchen: {kitchen.label} · {kitchen.active_orders} in queue
                    </span>
                    <span className="flex items-center gap-1.5 rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-1.5 text-xs font-semibold text-ink-200">
                        <span className={cn('size-2 rounded-full', pos.connected ? 'bg-go-400' : 'bg-brand-500')} />
                        POS: {pos.label}
                    </span>
                </div>
                {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
            </header>

            <main className={cn('px-4 py-6', !wide && 'mx-auto max-w-6xl')}>{children}</main>

            <Flash />
        </div>
    );
}
