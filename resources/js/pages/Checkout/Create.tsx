import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Banknote, Bike, Building2, CreditCard, ShoppingBag, Wallet, Zap, type LucideIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { TicketPanel } from '@/components/storefront/TicketPanel';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Checkbox, Input, Textarea } from '@/components/ui/Form';
import StorefrontLayout from '@/layouts/StorefrontLayout';
import { cartActions } from '@/lib/cart';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { CartZone, PaymentMethod } from '@/types';

interface SavedAddress {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    line1: string;
    line2: string | null;
    city: string;
    postcode: string;
    notes: string | null;
    delivery_zone_id: number | null;
}

interface CheckoutProps {
    zones: (CartZone & { areas: string | null })[];
    paymentMethods: { value: PaymentMethod; label: string; available: boolean; reason: string | null }[];
    customer: { name: string; phone: string; email: string };
    addresses: SavedAddress[];
}

const paymentIcons: Record<PaymentMethod, LucideIcon> = {
    fpx: Building2,
    card: CreditCard,
    ewallet: Wallet,
    cash: Banknote,
};

const paymentHints: Record<PaymentMethod, string> = {
    fpx: 'Pay from your bank account',
    card: 'Visa / Mastercard',
    ewallet: 'Touch ’n Go, GrabPay, Boost',
    cash: 'Pay when you collect',
};

export default function CheckoutCreate({ zones, paymentMethods, customer, addresses }: CheckoutProps) {
    const { cart, shop, auth, kitchen } = usePage().props;
    const delivery = cart?.type === 'delivery';

    const form = useForm({
        customer_name: customer.name,
        customer_phone: customer.phone,
        customer_email: customer.email,
        notes: '',
        payment_method: (paymentMethods.find((option) => option.available)?.value ?? 'fpx') as PaymentMethod,
        recipient_name: '',
        recipient_phone: '',
        line1: '',
        line2: '',
        city: '',
        postcode: '',
        delivery_notes: '',
        save_address: false,
    });

    if (!cart) return null;

    // Switching to delivery (or a bigger total) can make the chosen method unavailable: fall back to the first that works.
    const usable = paymentMethods.filter((option) => option.available);
    const method = usable.some((option) => option.value === form.data.payment_method) ? form.data.payment_method : usable[0]?.value;
    const blocked = cart.lines.length === 0 || cart.min_order_shortfall > 0 || !shop.is_open || !method;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, payment_method: method }));
        form.post(route('checkout.store'), { preserveScroll: true });
    };

    const applyAddress = (address: SavedAddress) => {
        form.setData({
            ...form.data,
            recipient_name: address.recipient_name,
            recipient_phone: address.phone,
            line1: address.line1,
            line2: address.line2 ?? '',
            city: address.city,
            postcode: address.postcode,
            delivery_notes: address.notes ?? '',
        });

        if (address.delivery_zone_id && zones.some((zone) => zone.id === address.delivery_zone_id)) {
            cartActions.setZone(address.delivery_zone_id);
        }
    };

    const choice = (active: boolean) =>
        cn(
            'flex w-full items-start gap-3 rounded-xl border p-3.5 text-left transition-colors',
            active ? 'border-brand-500 bg-brand-900' : 'border-ink-600 bg-ink-900 hover:border-ink-400',
        );

    return (
        <StorefrontLayout>
            <Head title="Checkout" />

            <Link href={route('menu.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-300 hover:text-white">
                <ArrowLeft className="size-4" aria-hidden /> Back to menu
            </Link>
            <h1 className="mb-6 font-display text-5xl leading-none text-white uppercase">Checkout</h1>

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div className="space-y-5">
                    <Section step={1} title="Your details" aside={!auth.user && <Link href={route('login')} className="text-xs font-semibold text-flame-400 hover:underline">Have an account? Sign in</Link>}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input label="Name" autoComplete="name" required value={form.data.customer_name} onChange={(event) => form.setData('customer_name', event.target.value)} error={form.errors.customer_name} />
                            <Input
                                label="Mobile number"
                                type="tel"
                                autoComplete="tel"
                                placeholder="012-345 6789"
                                hint="Order updates are sent here."
                                required
                                value={form.data.customer_phone}
                                onChange={(event) => form.setData('customer_phone', event.target.value)}
                                error={form.errors.customer_phone}
                            />
                            <Input
                                label="Email (optional)"
                                type="email"
                                autoComplete="email"
                                wrapperClassName="sm:col-span-2"
                                value={form.data.customer_email}
                                onChange={(event) => form.setData('customer_email', event.target.value)}
                                error={form.errors.customer_email}
                            />
                        </div>
                    </Section>

                    <Section step={2} title="How do you want it?">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {shop.pickup_enabled && (
                                <button type="button" className={choice(!delivery)} onClick={() => cartActions.setType('pickup')} aria-pressed={!delivery}>
                                    <ShoppingBag className="mt-0.5 size-5 shrink-0 text-flame-400" aria-hidden />
                                    <span>
                                        <span className="block text-sm font-bold text-white">Self Pickup</span>
                                        <span className="block text-xs text-ink-400">
                                            Ready in about {kitchen.eta_min}–{kitchen.eta_max} min at {shop.counter}
                                        </span>
                                    </span>
                                </button>
                            )}
                            {shop.delivery_enabled && zones.length > 0 && (
                                <button type="button" className={choice(delivery)} onClick={() => cartActions.setType('delivery')} aria-pressed={delivery}>
                                    <Bike className="mt-0.5 size-5 shrink-0 text-flame-400" aria-hidden />
                                    <span>
                                        <span className="block text-sm font-bold text-white">Delivery</span>
                                        <span className="block text-xs text-ink-400">Our rider brings it to your door</span>
                                    </span>
                                </button>
                            )}
                        </div>

                        {delivery ? (
                            <div className="mt-5 space-y-4">
                                <fieldset>
                                    <legend className="mb-2 text-xs font-semibold text-ink-300">Delivery area</legend>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {zones.map((zone) => (
                                            <button key={zone.id} type="button" className={choice(cart.zone?.id === zone.id)} onClick={() => cartActions.setZone(zone.id)} aria-pressed={cart.zone?.id === zone.id}>
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-bold text-white">{zone.name}</span>
                                                    {zone.areas && <span className="block text-xs text-ink-400">{zone.areas}</span>}
                                                    <span className="mt-1 block text-xs font-bold text-flame-400">
                                                        {money(zone.fee)} · ~{zone.eta_minutes} min
                                                    </span>
                                                    {zone.min_order > 0 && <span className="block text-[11px] text-ink-500">Min. order {money(zone.min_order)}</span>}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </fieldset>

                                {addresses.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-semibold text-ink-300">Saved addresses</p>
                                        <div className="flex flex-wrap gap-2">
                                            {addresses.map((address) => (
                                                <button
                                                    key={address.id}
                                                    type="button"
                                                    onClick={() => applyAddress(address)}
                                                    className="rounded-lg border border-ink-600 bg-ink-900 px-3 py-2 text-left text-xs hover:border-flame-400"
                                                >
                                                    <span className="block font-bold text-white">{address.label}</span>
                                                    <span className="text-ink-400">{address.line1}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input label="Address" autoComplete="address-line1" placeholder="House / unit no. and street" required wrapperClassName="sm:col-span-2" value={form.data.line1} onChange={(event) => form.setData('line1', event.target.value)} error={form.errors.line1} />
                                    <Input label="Building, floor, landmark (optional)" autoComplete="address-line2" wrapperClassName="sm:col-span-2" value={form.data.line2} onChange={(event) => form.setData('line2', event.target.value)} error={form.errors.line2} />
                                    <Input label="City" autoComplete="address-level2" required value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} error={form.errors.city} />
                                    <Input label="Postcode" inputMode="numeric" maxLength={5} autoComplete="postal-code" required value={form.data.postcode} onChange={(event) => form.setData('postcode', event.target.value)} error={form.errors.postcode} />
                                    <Input label="Recipient name (if not you)" value={form.data.recipient_name} onChange={(event) => form.setData('recipient_name', event.target.value)} error={form.errors.recipient_name} />
                                    <Input label="Recipient mobile (if not you)" type="tel" value={form.data.recipient_phone} onChange={(event) => form.setData('recipient_phone', event.target.value)} error={form.errors.recipient_phone} />
                                    <Input label="Note for the rider (optional)" placeholder="e.g. Leave with the guard" wrapperClassName="sm:col-span-2" value={form.data.delivery_notes} onChange={(event) => form.setData('delivery_notes', event.target.value)} error={form.errors.delivery_notes} />
                                </div>
                                {auth.user && <Checkbox label="Save this address to my account" checked={form.data.save_address} onChange={(event) => form.setData('save_address', event.target.checked)} />}
                            </div>
                        ) : (
                            <p className="mt-4 rounded-lg bg-ink-900 px-3 py-2.5 text-xs text-ink-300">
                                Collect from <strong className="text-white">{shop.address}</strong>. Show your pickup code at {shop.counter}.
                            </p>
                        )}
                    </Section>

                    <Section step={3} title="Payment">
                        <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Payment method">
                            {paymentMethods.map((option) => {
                                const Icon = paymentIcons[option.value];
                                const unavailable = !option.available;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={method === option.value}
                                        disabled={unavailable}
                                        onClick={() => form.setData('payment_method', option.value)}
                                        className={cn(choice(method === option.value), unavailable && 'cursor-not-allowed opacity-40')}
                                    >
                                        <Icon className="mt-0.5 size-5 shrink-0 text-flame-400" aria-hidden />
                                        <span>
                                            <span className="block text-sm font-bold text-white">{option.label}</span>
                                            <span className="block text-xs text-ink-400">{option.reason ?? paymentHints[option.value]}</span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {form.errors.payment_method && <p className="mt-2 text-xs font-medium text-brand-400">{form.errors.payment_method}</p>}
                        {!auth.user && paymentMethods.some((option) => option.value === 'cash' && option.reason?.startsWith('Sign in')) && (
                            <p className="mt-3 text-xs text-ink-400">
                                Want to pay at the counter?{' '}
                                <Link href={route('login')} className="font-semibold text-flame-400 hover:underline">
                                    Sign in
                                </Link>{' '}
                                or{' '}
                                <Link href={route('register')} className="font-semibold text-flame-400 hover:underline">
                                    create an account
                                </Link>{' '}
                                — your ticket is kept.
                            </p>
                        )}
                    </Section>

                    <Section step={4} title="Anything else?">
                        <Textarea label="Order notes (optional)" placeholder="Allergies, cutlery, extra tissue..." maxLength={500} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} error={form.errors.notes} />
                    </Section>
                </div>

                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <TicketPanel
                        action={
                            <Button type="submit" size="lg" className="w-full" loading={form.processing} disabled={blocked} icon={<Zap className="size-4" aria-hidden />}>
                                {method === 'cash' ? 'Place Order' : 'Place Order & Pay'} · {money(cart.total)}
                            </Button>
                        }
                    />
                    <p className="mt-3 text-center text-[11px] text-ink-500">
                        By placing this order you agree to our{' '}
                        <Link href={route('legal.terms')} className="underline hover:text-ink-200">
                            Terms
                        </Link>{' '}
                        and{' '}
                        <Link href={route('legal.privacy')} className="underline hover:text-ink-200">
                            Privacy Notice
                        </Link>
                        .
                    </p>
                </aside>
            </form>
        </StorefrontLayout>
    );
}

function Section({ step, title, aside, children }: { step: number; title: string; aside?: ReactNode; children: ReactNode }) {
    return (
        <Card className="p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2.5 text-base font-bold text-white">
                    <span className="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-extrabold">{step}</span>
                    {title}
                </h2>
                {aside}
            </div>
            {children}
        </Card>
    );
}
