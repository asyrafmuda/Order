import { Head, useForm } from '@inertiajs/react';
import { Bell, Clock, Percent, ShoppingBag, Store } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';

interface Settings {
    shop: { name: string; tagline: string | null; address: string; phone: string; whatsapp: string; maps_url: string | null; counter: string };
    hours: { open: string; close: string; last_order: string };
    ordering: { enabled: boolean; pickup_enabled: boolean; delivery_enabled: boolean; pickup_eta_min: number; pickup_eta_max: number; queue_prefix: string };
    tax: { rate: number; inclusive: boolean };
    notifications: { enabled: boolean };
}

export default function SettingsEdit({ settings }: { settings: Settings }) {
    const form = useForm({
        shop: { ...settings.shop, tagline: settings.shop.tagline ?? '', maps_url: settings.shop.maps_url ?? '' },
        hours: settings.hours,
        ordering: { ...settings.ordering, pickup_eta_min: String(settings.ordering.pickup_eta_min), pickup_eta_max: String(settings.ordering.pickup_eta_max) },
        tax: { ...settings.tax, rate: String(settings.tax.rate) },
        notifications: settings.notifications,
    });

    const { data } = form;
    const errors = form.errors as Record<string, string | undefined>;

    // Update one key inside a settings group, e.g. set('shop', 'name', 'Boss Burger').
    const set = <G extends keyof typeof data, K extends keyof (typeof data)[G]>(group: G, key: K, value: (typeof data)[G][K]) =>
        form.setData((current) => ({ ...current, [group]: { ...current[group], [key]: value } }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
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

                <Section title="Ordering" icon={<ShoppingBag className="size-4" />}>
                    <div className="space-y-4">
                        <Toggle
                            checked={data.ordering.enabled}
                            onChange={(checked) => set('ordering', 'enabled', checked)}
                            label="Accept online orders"
                            description="Switch off to pause ordering immediately, e.g. when the kitchen is overwhelmed."
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Toggle checked={data.ordering.pickup_enabled} onChange={(checked) => set('ordering', 'pickup_enabled', checked)} label="Self pickup" />
                            <Toggle checked={data.ordering.delivery_enabled} onChange={(checked) => set('ordering', 'delivery_enabled', checked)} label="Delivery" description="Also needs at least one active delivery zone." />
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
                    </div>
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

function Section({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
    return (
        <Card>
            <CardHeader title={title} icon={icon} />
            <div className="p-4">{children}</div>
        </Card>
    );
}
