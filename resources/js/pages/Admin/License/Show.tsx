import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, KeyRound, RefreshCw, ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Input } from '@/components/ui/Form';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { dateTime } from '@/lib/format';

interface LicenseInfo {
    status: string;
    label: string;
    help: string;
    usable: boolean;
    configured: boolean;
    exempt: boolean;
    /** The address this page was opened on. */
    host: string;
    /** The address the license is activated for. */
    domain: string | null;
    customer: string | null;
    code: string | null;
    activated_at: string | null;
    checked_at: string | null;
    message: string | null;
}

export default function LicenseShow({ license }: { license: LicenseInfo }) {
    const form = useForm({ code: '' });
    const activated = license.domain !== null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('admin.license.activate'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <BackOfficeLayout title="License" description="The purchase code this copy runs on">
            <Head title="License" />

            <div className="mx-auto max-w-2xl space-y-5">
                <Card className={license.usable ? undefined : 'border-danger-600/50'}>
                    <div className="flex items-start gap-3 p-4">
                        {license.usable ? (
                            <BadgeCheck className="mt-0.5 size-6 shrink-0 text-go-400" aria-hidden />
                        ) : (
                            <ShieldAlert className="mt-0.5 size-6 shrink-0 text-danger-400" aria-hidden />
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="flex flex-wrap items-center gap-2 text-base font-bold text-strong">
                                {license.label}
                                <Badge tone={license.usable ? 'green' : 'red'}>{license.usable ? 'Site running' : 'Site stopped'}</Badge>
                            </p>
                            {license.help && <p className="mt-1 text-sm text-ink-300">{license.help}</p>}
                            {license.message && <p className="mt-1 text-sm text-danger-400">{license.message}</p>}
                            {!license.usable && <p className="mt-2 text-xs text-ink-400">Customers and staff see that the site is unavailable until the license is sorted out. You can still sign in and use this page.</p>}
                        </div>
                    </div>

                    {activated && (
                        <dl className="grid gap-x-4 gap-y-3 border-t border-ink-700 p-4 text-sm sm:grid-cols-2">
                            <Item label="Activated for" value={license.domain} />
                            <Item label="Licensed to" value={license.customer} />
                            <Item label="Purchase code" value={license.code} mono />
                            <Item label="Last confirmed" value={license.checked_at ? dateTime(license.checked_at) : null} />
                        </dl>
                    )}

                    {activated && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-ink-700 px-4 py-3">
                            <Button variant="secondary" size="sm" onClick={() => router.post(route('admin.license.check'), {}, { preserveScroll: true })} icon={<RefreshCw className="size-4" aria-hidden />}>
                                Check now
                            </Button>
                            <span className="ml-auto">
                                <ConfirmButton
                                    size="sm"
                                    title="Release this domain?"
                                    message={`The purchase code stops working on ${license.domain} straight away and this site stops. Do this only when moving to a new web address, then activate the code there.`}
                                    confirmLabel="Release domain"
                                    onConfirm={() => router.delete(route('admin.license.release'), { preserveScroll: true })}
                                >
                                    Move to another domain
                                </ConfirmButton>
                            </span>
                        </div>
                    )}
                </Card>

                {license.exempt ? (
                    <Card className="p-4 text-sm text-ink-300">
                        You are using a local address ({license.host}), which never needs a license. Activate the purchase code on the live web address.
                    </Card>
                ) : (
                    !license.usable && (
                        <Card>
                            <CardHeader title={activated ? 'Use a different purchase code' : 'Activate'} icon={<KeyRound className="size-4" />} />
                            <form onSubmit={submit} className="space-y-3 p-4">
                                <Input
                                    label="Purchase code"
                                    required
                                    autoFocus
                                    placeholder="XXXX-XXXX-XXXX-XXXX"
                                    autoComplete="off"
                                    className="font-mono tracking-wider uppercase"
                                    hint={`It will be tied to ${license.host}.`}
                                    value={form.data.code}
                                    onChange={(event) => form.setData('code', event.target.value)}
                                    error={form.errors.code}
                                />
                                <Button type="submit" loading={form.processing}>
                                    Activate
                                </Button>
                            </form>
                        </Card>
                    )
                )}
            </div>
        </BackOfficeLayout>
    );
}

function Item({ label, value, mono }: { label: string; value: string | null; mono?: boolean }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-ink-400">{label}</dt>
            <dd className={`truncate font-semibold text-ink-100 ${mono ? 'font-mono tracking-wider' : ''}`}>{value ?? '—'}</dd>
        </div>
    );
}
