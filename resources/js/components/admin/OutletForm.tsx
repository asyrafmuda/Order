import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { OutletFields, toOutletForm, type OutletSettings } from '@/components/admin/OutletFields';
import { Input, Toggle } from '@/components/ui/Form';

export interface Outlet {
    id: number;
    name: string;
    slug: string;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
    settings: OutletSettings;
}

interface OutletFormProps {
    /** Null when adding an outlet; `template` then supplies sensible starting values. */
    outlet: Outlet | null;
    template: OutletSettings;
    /** "current": a manager (or admin) saving the outlet they are working in. */
    mode: 'create' | 'update' | 'current';
    /** Name, order and active switch are for super admins. */
    identity: boolean;
    formId: string;
    onSuccess?: () => void;
    onProcessing?: (processing: boolean) => void;
    children?: ReactNode;
}

/** One outlet: its name plus its own address, hours and ways of ordering. */
export function OutletForm({ outlet, template, mode, identity, formId, onSuccess, onProcessing, children }: OutletFormProps) {
    const form = useForm({
        name: outlet?.name ?? '',
        is_active: outlet?.is_active ?? true,
        sort_order: String(outlet?.sort_order ?? 1),
        ...toOutletForm(outlet?.settings ?? template),
    });

    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess, onStart: () => onProcessing?.(true), onFinish: () => onProcessing?.(false) };

        if (mode === 'create') {
            form.post(route('admin.outlets.store'), options);
        } else if (mode === 'update' && outlet) {
            form.put(route('admin.outlets.update', outlet.id), options);
        } else {
            form.put(route('admin.outlet.update'), options);
        }
    };

    return (
        <form id={formId} onSubmit={submit} className="space-y-5">
            {identity && (
                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                    <Input label="Outlet name" required autoFocus={mode === 'create'} placeholder="e.g. Shah Alam" hint="Customers see this when choosing where to order from." value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={errors.name} />
                    <Input label="Sort order" type="number" min={0} required value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} error={errors.sort_order} />
                    {!outlet?.is_default && (
                        <div className="sm:col-span-2">
                            <Toggle
                                checked={form.data.is_active}
                                onChange={(checked) => form.setData('is_active', checked)}
                                label="Open to customers"
                                description="Inactive outlets are hidden from customers. Staff can still finish the orders already in."
                            />
                            {errors.is_active && <p className="mt-1 text-xs font-medium text-brand-400">{errors.is_active}</p>}
                        </div>
                    )}
                </div>
            )}

            <OutletFields data={form.data} errors={errors} set={(group, key, value) => form.setData((current) => ({ ...current, [group]: { ...current[group], [key]: value } }))} />

            {children}
        </form>
    );
}
