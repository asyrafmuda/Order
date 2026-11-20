import { usePage } from '@inertiajs/react';
import { Bike, ChefHat, MapPin, ShoppingBag } from 'lucide-react';
import { cn } from '@/lib/cn';
import { cartActions } from '@/lib/cart';
import { money } from '@/lib/format';
import type { CartZone } from '@/types';

interface OrderModeBarProps {
    zones: (CartZone & { areas: string | null })[];
}

/** Pickup / delivery switch with the shop, POS and kitchen status chips. */
export function OrderModeBar({ zones }: OrderModeBarProps) {
    const { shop, cart, kitchen, pos } = usePage().props;
    const type = cart?.type ?? 'pickup';
    const zone = cart?.zone;

    const mode = (active: boolean) =>
        cn(
            'flex h-9 shrink-0 items-center gap-2 rounded-lg px-3 text-xs font-bold transition-colors',
            active ? 'bg-brand-600 text-white shadow-glow' : 'bg-ink-800 text-ink-300 hover:bg-ink-700',
        );

    return (
        <div className="no-print border-b border-ink-700 bg-ink-900">
            <div className="scrollbar-none mx-auto flex max-w-7xl items-center gap-2 overflow-x-auto px-4 py-2.5">
                {shop.pickup_enabled && (
                    <button type="button" className={mode(type === 'pickup')} onClick={() => cartActions.setType('pickup')} aria-pressed={type === 'pickup'}>
                        <ShoppingBag className="size-4" aria-hidden />
                        Self Pickup ({kitchen.eta_min}–{kitchen.eta_max} min)
                    </button>
                )}
                {shop.delivery_enabled && zones.length > 0 && (
                    <button type="button" className={mode(type === 'delivery')} onClick={() => cartActions.setType('delivery')} aria-pressed={type === 'delivery'}>
                        <Bike className="size-4" aria-hidden />
                        Delivery ({(zone?.eta_minutes ?? zones[0].eta_minutes) - 10}–{zone?.eta_minutes ?? zones[0].eta_minutes} min)
                    </button>
                )}

                {type === 'delivery' && zone ? (
                    <>
                        <label className="flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-ink-700 bg-ink-850 pr-1 pl-2.5 text-xs text-ink-300">
                            <MapPin className="size-3.5 text-brand-400" aria-hidden />
                            <span className="sr-only">Delivery area</span>
                            <select
                                value={zone.id}
                                onChange={(event) => cartActions.setZone(Number(event.target.value))}
                                className="h-full bg-transparent pr-1 font-semibold text-ink-100 focus:outline-none"
                            >
                                {zones.map((option) => (
                                    <option key={option.id} value={option.id} className="bg-ink-850">
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <span className="flex h-9 shrink-0 items-center rounded-lg bg-flame-900 px-2.5 text-xs font-extrabold text-flame-400 ring-1 ring-flame-500/30 ring-inset">
                            +{money(zone.fee)}
                        </span>
                    </>
                ) : (
                    <span className="flex h-9 shrink-0 items-center gap-1.5 px-1 text-xs text-ink-300">
                        <MapPin className="size-3.5 text-brand-400" aria-hidden />
                        {shop.address}
                    </span>
                )}

                <span className="ml-auto flex h-9 shrink-0 items-center gap-2 rounded-lg border border-go-500/30 bg-go-900 px-3 text-[11px] font-extrabold tracking-wide text-go-400 uppercase">
                    <span className={cn('size-2 rounded-full', pos.connected ? 'animate-pulse-soft bg-go-400' : 'bg-flame-400')} />
                    POS {pos.simulated ? 'demo mode' : pos.connected ? 'connected' : 'offline'}
                </span>
                <span className="flex h-9 shrink-0 items-center gap-1.5 text-xs text-ink-300">
                    <ChefHat className="size-4 text-flame-400" aria-hidden />
                    Kitchen: <strong className="font-semibold text-ink-100">{kitchen.label}</strong> ({kitchen.active_orders} in queue)
                </span>
            </div>
        </div>
    );
}
