import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Bell, Clock, Palette, Percent, ShoppingBag, Store, Trash2, Upload, Wallet, Zap } from 'lucide-react';
import { useRef, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { MoneyInput } from '@/components/admin/MoneyInput';
import { Input, Textarea, Toggle } from '@/components/ui/Form';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { inputToSen, senToInput } from '@/lib/format';

interface Settings {
    shop: { name: string; tagline: string | null; address: string; phone: string; whatsapp: string; maps_url: string | null; counter: string };
    brand: { icon: string; primary_color: string; accent_color: string; footer_text: string | null; badge_text: string | null };
    hours: { open: string; close: string; last_order: string };
    ordering: { enabled: boolean; pickup_enabled: boolean; delivery_enabled: boolean; dine_in_enabled: boolean; pickup_eta_min: number; pickup_eta_max: number; queue_prefix: string; auto_close_hours: number };
    payments: { fpx_enabled: boolean; card_enabled: boolean; ewallet_enabled: boolean; cash_enabled: boolean; cash_requires_account: boolean; cash_max_amount: number; dine_in_pay_at_counter: boolean };
    pos: { quick_notes: string[] };
    tax: { rate: number; inclusive: boolean };
    notifications: { enabled: boolean };
}

export default function SettingsEdit({ settings }: { settings: Settings }) {
    const form = useForm({
        shop: { ...settings.shop, tagline: settings.shop.tagline ?? '', maps_url: settings.shop.maps_url ?? '' },
        brand: { ...settings.brand, footer_text: settings.brand.footer_text ?? '', badge_text: settings.brand.badge_text ?? '' },
        hours: settings.hours,
        ordering: {
            ...settings.ordering,
            pickup_eta_min: String(settings.ordering.pickup_eta_min),
            pickup_eta_max: String(settings.ordering.pickup_eta_max),
            auto_close_hours: String(settings.ordering.auto_close_hours),
        },
        payments: { ...settings.payments, cash_max_amount: senToInput(settings.payments.cash_max_amount) },
        pos: { quick_notes: (settings.pos?.quick_notes ?? []).join('\n') },
        tax: { ...settings.tax, rate: String(settings.tax.rate) },
        notifications: settings.notifications,
    });

    const { data } = form;
    const { shop, errors: pageErrors } = usePage<{ errors: Record<string, string | undefined> }>().props;
    const logoInput = useRef<HTMLInputElement>(null);

    const uploadLogo = (file: File | undefined) => {
        if (file) {
            router.post(route('admin.settings.logo.store'), { logo: file }, { preserveScroll: true, forceFormData: true });
        }
    };
    const errors = form.errors as Record<string, string | undefined>;

    // Update one key inside a settings group, e.g. set('shop', 'name', 'Boss Burger').
    const set = <G extends keyof typeof data, K extends keyof (typeof data)[G]>(group: G, key: K, value: (typeof data)[G][K]) =>
        form.setData((current) => ({ ...current, [group]: { ...current[group], [key]: value } }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((current) => ({
            ...current,
            payments: { ...current.payments, cash_max_amount: inputToSen(current.payments.cash_max_amount) },
            pos: { quick_notes: current.pos.quick_notes.split('\n').map((note) => note.trim()).filter(Boolean) },
        }));
        form.put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <BackOfficeLayout
            title="Settings"
            description="Shop details, opening hours and ordering rules"
            actions={
                <Button type="submit" form="settings-form" size="sm" loading={form.processing}>
                    Save settings
                </Button>
            }
        >
            <Head title="Settings" />

            <form id="settings-form" onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
                <Section title="Shop" icon={<Store className="size-4" />}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input label="Shop name" required value={data.shop.name} onChange={(event) => set('shop', 'name', event.target.value)} error={errors['shop.name']} />
                        <Input label="Tagline" value={data.shop.tagline} onChange={(event) => set('shop', 'tagline', event.target.value)} error={errors['shop.tagline']} />
                        <Input label="Address" required wrapperClassName="sm:col-span-2" value={data.shop.address} onChange={(event) => set('shop', 'address', event.target.value)} error={errors['shop.address']} />
                        <Input label="Phone" required value={data.shop.phone} onChange={(event) => set('shop', 'phone', event.target.value)} error={errors['shop.phone']} />
                        <Input
                            label="WhatsApp number"
                            required
                            hint="Digits only, international format: 60123456789"
                            value={data.shop.whatsapp}
                            onChange={(event) => set('shop', 'whatsapp', event.target.value)}
                            error={errors['shop.whatsapp']}
                        />
                        <Input
                            label="Map link"
                            type="url"
                            placeholder="https://maps.google.com/?q=..."
                            value={data.shop.maps_url}
                            onChange={(event) => set('shop', 'maps_url', event.target.value)}
                            error={errors['shop.maps_url']}
                        />
                        <Input
                            label="Pickup counter"
                            required
                            hint="Where customers collect, e.g. “Counter 2”."
                            value={data.shop.counter}
                            onChange={(event) => set('shop', 'counter', event.target.value)}
                            error={errors['shop.counter']}
                        />
                    </div>
                </Section>

                <Section title="Opening hours" icon={<Clock className="size-4" />}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Input label="Opens" type="time" required value={data.hours.open} onChange={(event) => set('hours', 'open', event.target.value)} error={errors['hours.open']} />
                        <Input
                            label="Last order"
                            type="time"
                            required
                            hint="Online ordering stops at this time."
                            value={data.hours.last_order}
                            onChange={(event) => set('hours', 'last_order', event.target.value)}
                            error={errors['hours.last_order']}
                        />
                        <Input label="Closes" type="time" required value={data.hours.close} onChange={(event) => set('hours', 'close', event.target.value)} error={errors['hours.close']} />
                    </div>
                </Section>

                <Section title="Branding" icon={<Palette className="size-4" />}>
                    <div className="space-y-5">
                        <div className="flex flex-wrap items-center gap-4">
                            {shop.logo_url ? (
                                <img src={shop.logo_url} alt="Current logo" className="size-16 rounded-xl object-cover ring-1 ring-ink-600" />
                            ) : (
                                <span className="flex size-16 items-center justify-center rounded-xl bg-brand-600 text-3xl" aria-hidden>
                                    {data.brand.icon}
                                </span>
                            )}
                            <div className="space-y-2">
                                <div className="flex flex-wrap gap-2">
                                    <input ref={logoInput} type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={(event) => uploadLogo(event.target.files?.[0])} />
                                    <Button variant="secondary" size="sm" onClick={() => logoInput.current?.click()} icon={<Upload className="size-4" aria-hidden />}>
                                        {shop.logo_url ? 'Replace logo' : 'Upload logo'}
                                    </Button>
                                    {shop.logo_url && (
                                        <Button variant="ghost" size="sm" onClick={() => router.delete(route('admin.settings.logo.destroy'), { preserveScroll: true })} icon={<Trash2 className="size-4" aria-hidden />}>
                                            Remove
                                        </Button>
                                    )}
                                </div>
                                <p className="text-xs text-ink-400">Square PNG, JPG or WebP, up to 1 MB. Without a logo the icon below is shown.</p>
                                {pageErrors.logo && <p className="text-xs font-medium text-brand-400">{pageErrors.logo}</p>}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Input
                                label="Icon (emoji)"
                                required
                                maxLength={8}
                                hint="Also used for menu items without a photo."
                                value={data.brand.icon}
                                onChange={(event) => set('brand', 'icon', event.target.value)}
                                error={errors['brand.icon']}
                            />
                            <ColorField label="Main colour" hint="Buttons and highlights." value={data.brand.primary_color} onChange={(value) => set('brand', 'primary_color', value)} error={errors['brand.primary_color']} />
                            <ColorField label="Accent colour" hint="Prices and badges." value={data.brand.accent_color} onChange={(value) => set('brand', 'accent_color', value)} error={errors['brand.accent_color']} />
                        </div>

                        <Input
                            label="Footer text"
                            maxLength={255}
                            hint="One line about the shop, shown at the bottom of every customer page."
                            value={data.brand.footer_text}
                            onChange={(event) => set('brand', 'footer_text', event.target.value)}
                            error={errors['brand.footer_text']}
                        />
                        <Input
                            label="Badge"
                            maxLength={60}
                            wrapperClassName="sm:max-w-sm"
                            hint="A short assurance under the footer text, e.g. “100% Halal kitchen”. Leave empty to hide it."
                            value={data.brand.badge_text}
                            onChange={(event) => set('brand', 'badge_text', event.target.value)}
                            error={errors['brand.badge_text']}
                        />
                    </div>
                </Section>

                <Section title="Ordering" icon={<ShoppingBag className="size-4" />}>
                    <div className="space-y-4">
                        <Toggle
                            checked={data.ordering.enabled}
                            onChange={(checked) => set('ordering', 'enabled', checked)}
                            label="Accept online orders"
                            description="Switch off to pause ordering immediately, e.g. when the kitchen is overwhelmed."
                        />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Toggle checked={data.ordering.pickup_enabled} onChange={(checked) => set('ordering', 'pickup_enabled', checked)} label="Self pickup" description="Order from home, collect at the shop." />
                            <Toggle checked={data.ordering.delivery_enabled} onChange={(checked) => set('ordering', 'delivery_enabled', checked)} label="Delivery" description="Switch off if you do not deliver. Needs an active delivery zone." />
                            <Toggle checked={data.ordering.dine_in_enabled} onChange={(checked) => set('ordering', 'dine_in_enabled', checked)} label="Dine-in by table QR" description="Customers scan the code on their table. Set up under Tables & QR." />
                        </div>
                        {(errors['ordering.delivery_enabled'] || errors['ordering.pickup_enabled']) && (
                            <p className="text-xs font-medium text-brand-400">{errors['ordering.delivery_enabled'] ?? errors['ordering.pickup_enabled']}</p>
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Input
                                label="Prep time from (min)"
                                type="number"
                                min={1}
                                max={180}
                                required
                                value={data.ordering.pickup_eta_min}
                                onChange={(event) => set('ordering', 'pickup_eta_min', event.target.value)}
                                error={errors['ordering.pickup_eta_min']}
                            />
                            <Input
                                label="Prep time to (min)"
                                type="number"
                                min={1}
                                max={240}
                                required
                                hint="Shown as “15–20 min”; extra time is added when the kitchen is busy."
                                value={data.ordering.pickup_eta_max}
                                onChange={(event) => set('ordering', 'pickup_eta_max', event.target.value)}
                                error={errors['ordering.pickup_eta_max']}
                            />
                            <Input
                                label="Queue prefix"
                                required
                                maxLength={3}
                                hint="Order numbers look like A001 and restart daily."
                                value={data.ordering.queue_prefix}
                                onChange={(event) => set('ordering', 'queue_prefix', event.target.value.toUpperCase())}
                                error={errors['ordering.queue_prefix']}
                            />
                        </div>
                        <Input
                            label="Close uncollected orders after (hours)"
                            type="number"
                            min={0}
                            max={72}
                            required
                            wrapperClassName="sm:max-w-xs"
                            hint="Ready pickup orders nobody marked as collected: paid ones are completed, unpaid ones cancelled. 0 switches this off."
                            value={data.ordering.auto_close_hours}
                            onChange={(event) => set('ordering', 'auto_close_hours', event.target.value)}
                            error={errors['ordering.auto_close_hours']}
                        />
                    </div>
                </Section>

                <Section title="Payment methods (online checkout)" icon={<Wallet className="size-4" />}>
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Toggle checked={data.payments.fpx_enabled} onChange={(checked) => set('payments', 'fpx_enabled', checked)} label="FPX online banking" />
                            <Toggle checked={data.payments.card_enabled} onChange={(checked) => set('payments', 'card_enabled', checked)} label="Credit / debit card" />
                            <Toggle checked={data.payments.ewallet_enabled} onChange={(checked) => set('payments', 'ewallet_enabled', checked)} label="E-wallet" />
                        </div>
                        <div className="rounded-lg border border-ink-700 bg-ink-900 p-4">
                            <Toggle
                                checked={data.payments.cash_enabled}
                                onChange={(checked) => set('payments', 'cash_enabled', checked)}
                                label="Cash at counter"
                                description="These orders reach the kitchen before anyone has paid, so keep the limits below tight."
                            />
                            {errors['payments.cash_enabled'] && <p className="mt-2 text-xs font-medium text-brand-400">{errors['payments.cash_enabled']}</p>}
                            {data.payments.cash_enabled && (
                                <div className="mt-4 grid gap-4 sm:grid-cols-2 sm:items-start">
                                    <Toggle
                                        checked={data.payments.cash_requires_account}
                                        onChange={(checked) => set('payments', 'cash_requires_account', checked)}
                                        label="Only for signed-in customers"
                                        description="Guests must pay online."
                                    />
                                    <MoneyInput
                                        label="Largest cash order"
                                        hint="Bigger orders must be paid online. 0 means no limit."
                                        value={data.payments.cash_max_amount}
                                        onChange={(value) => set('payments', 'cash_max_amount', value)}
                                        error={errors['payments.cash_max_amount']}
                                    />
                                </div>
                            )}
                        </div>
                        <Toggle
                            checked={data.payments.dine_in_pay_at_counter}
                            onChange={(checked) => set('payments', 'dine_in_pay_at_counter', checked)}
                            label="Table orders can pay at the counter"
                            description="Seated customers order first and pay before they leave, without an account. Off: table orders must be paid online."
                        />
                    </div>
                </Section>

                <Section title="Counter POS" icon={<Zap className="size-4" />}>
                    <Textarea
                        label="Quick kitchen notes"
                        rows={5}
                        hint="One per line, up to 12. They appear as one-tap buttons when taking an order at the counter."
                        value={data.pos.quick_notes}
                        onChange={(event) => set('pos', 'quick_notes', event.target.value)}
                        error={errors['pos.quick_notes'] ?? Object.entries(errors).find(([key]) => key.startsWith('pos.quick_notes.'))?.[1]}
                    />
                </Section>

                <Section title="Tax" icon={<Percent className="size-4" />}>
                    <div className="grid gap-4 sm:grid-cols-2 sm:items-start">
                        <Input
                            label="SST rate (%)"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            required
                            hint="Use 0 if you do not charge SST."
                            value={data.tax.rate}
                            onChange={(event) => set('tax', 'rate', event.target.value)}
                            error={errors['tax.rate']}
                        />
                        <div className="sm:pt-6">
                            <Toggle
                                checked={data.tax.inclusive}
                                onChange={(checked) => set('tax', 'inclusive', checked)}
                                label="Menu prices include SST"
                                description="Off: SST is added on top of the subtotal at checkout."
                            />
                        </div>
                    </div>
                </Section>

                <Section title="Notifications" icon={<Bell className="size-4" />}>
                    <Toggle
                        checked={data.notifications.enabled}
                        onChange={(checked) => set('notifications', 'enabled', checked)}
                        label="Message customers about their order"
                        description="Confirmed, ready, out for delivery, completed and cancelled updates."
                    />
                </Section>

                <div className="flex justify-end">
                    <Button type="submit" size="lg" loading={form.processing}>
                        Save settings
                    </Button>
                </div>
            </form>
        </BackOfficeLayout>
    );
}

function ColorField({ label, hint, value, onChange, error }: { label: string; hint: string; value: string; onChange: (value: string) => void; error?: string }) {
    return (
        <div className="space-y-1.5">
            <span className="block text-xs font-semibold text-ink-300">{label}</span>
            <label className="flex h-10 items-center gap-2 rounded-lg border border-ink-600 bg-ink-900 px-2">
                <input type="color" value={/^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000'} onChange={(event) => onChange(event.target.value)} className="size-7 shrink-0 cursor-pointer rounded border-0 bg-transparent p-0" aria-label={label} />
                <input type="text" value={value} maxLength={7} onChange={(event) => onChange(event.target.value)} className="w-full bg-transparent font-mono text-sm text-ink-100 focus:outline-none" aria-label={`${label} hex code`} />
            </label>
            {error ? <p className="text-xs font-medium text-brand-400">{error}</p> : <p className="text-xs text-ink-400">{hint}</p>}
        </div>
    );
}

function Section({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
    return (
        <Card>
            <CardHeader title={title} icon={icon} />
            <div className="p-4">{children}</div>
        </Card>
    );
}
