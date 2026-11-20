import { Clock, MapPin, ShoppingBag } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';

/** The settings that belong to one outlet, as the form holds them (numbers as typed strings). */
export interface OutletFormSettings {
    shop: { address: string; phone: string; whatsapp: string; maps_url: string; counter: string };
    hours: { open: string; close: string; last_order: string };
    ordering: {
        enabled: boolean;
        pickup_enabled: boolean;
        delivery_enabled: boolean;
        dine_in_enabled: boolean;
        pickup_eta_min: string;
        pickup_eta_max: string;
        queue_prefix: string;
    };
}

/** Settings as the server sends them. */
export interface OutletSettings {
    shop: { address: string; phone: string; whatsapp: string; maps_url: string | null; counter: string };
    hours: { open: string; close: string; last_order: string };
    ordering: {
        enabled: boolean;
        pickup_enabled: boolean;
        delivery_enabled: boolean;
        dine_in_enabled: boolean;
        pickup_eta_min: number;
        pickup_eta_max: number;
        queue_prefix: string;
    };
}

export function toOutletForm(settings: OutletSettings): OutletFormSettings {
    return {
        shop: {
            address: settings.shop.address ?? '',
            phone: settings.shop.phone ?? '',
            whatsapp: settings.shop.whatsapp ?? '',
            maps_url: settings.shop.maps_url ?? '',
            counter: settings.shop.counter ?? '',
        },
        hours: { ...settings.hours },
        ordering: {
            ...settings.ordering,
            pickup_eta_min: String(settings.ordering.pickup_eta_min),
            pickup_eta_max: String(settings.ordering.pickup_eta_max),
        },
    };
}

type Group = keyof OutletFormSettings;

interface OutletFieldsProps {
    data: OutletFormSettings;
    errors: Record<string, string | undefined>;
    set: <G extends Group, K extends keyof OutletFormSettings[G]>(group: G, key: K, value: OutletFormSettings[G][K]) => void;
}

/**
 * Where an outlet is, when it is open and how customers can order from it.
 * Shown under Settings for a single-outlet business, and once per outlet
 * when there are several.
 */
export function OutletFields({ data, errors, set }: OutletFieldsProps) {
    return (
        <>
            <SettingsSection title="Location & contact" icon={<MapPin className="size-4" />}>
                <div className="grid gap-4 sm:grid-cols-2">
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
            </SettingsSection>

            <SettingsSection title="Opening hours" icon={<Clock className="size-4" />}>
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
            </SettingsSection>

            <SettingsSection title="Ways of ordering" icon={<ShoppingBag className="size-4" />}>
                <div className="space-y-4">
                    <Toggle
                        checked={data.ordering.enabled}
                        onChange={(checked) => set('ordering', 'enabled', checked)}
                        label="Accept online orders"
                        description="Switch off to pause ordering immediately, e.g. when the kitchen is overwhelmed. The counter POS keeps working."
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Toggle checked={data.ordering.pickup_enabled} onChange={(checked) => set('ordering', 'pickup_enabled', checked)} label="Self pickup" description="Order from home, collect at the shop." />
                        <Toggle checked={data.ordering.delivery_enabled} onChange={(checked) => set('ordering', 'delivery_enabled', checked)} label="Delivery" description="Switch off if you do not deliver. Needs an active delivery zone." />
                        <Toggle checked={data.ordering.dine_in_enabled} onChange={(checked) => set('ordering', 'dine_in_enabled', checked)} label="Dine-in by table QR" description="Customers scan the code on their table. Set up under Tables & QR." />
                    </div>
                    {(errors['ordering.delivery_enabled'] || errors['ordering.pickup_enabled']) && (
                        <p className="text-xs font-medium text-danger-400">{errors['ordering.delivery_enabled'] ?? errors['ordering.pickup_enabled']}</p>
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
            </SettingsSection>
        </>
    );
}

export function SettingsSection({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
    return (
        <Card>
            <CardHeader title={title} icon={icon} />
            <div className="p-4">{children}</div>
        </Card>
    );
}
