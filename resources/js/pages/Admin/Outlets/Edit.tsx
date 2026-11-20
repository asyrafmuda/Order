import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { OutletForm, type Outlet } from '@/components/admin/OutletForm';
import { Button } from '@/components/ui/Button';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';

/** The outlet being managed: where it is, when it is open and how customers can order from it. */
export default function OutletEdit({ outlet }: { outlet: Outlet }) {
    const { auth } = usePage().props;
    const [saving, setSaving] = useState(false);

    return (
        <BackOfficeLayout
            title={outlet.name}
            description="Address, opening hours and ways of ordering for this outlet"
            actions={
                <Button type="submit" form="outlet-form" size="sm" loading={saving}>
                    Save outlet
                </Button>
            }
        >
            <Head title={`${outlet.name} details`} />

            <div className="mx-auto max-w-3xl">
                <OutletForm formId="outlet-form" outlet={outlet} template={outlet.settings} mode="current" identity={Boolean(auth.user?.is_admin)} onProcessing={setSaving}>
                    <div className="flex justify-end">
                        <Button type="submit" size="lg" loading={saving}>
                            Save outlet
                        </Button>
                    </div>
                </OutletForm>
            </div>
        </BackOfficeLayout>
    );
}
