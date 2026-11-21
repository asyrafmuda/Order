import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Copy, Globe, KeyRound, Plus, RotateCcw, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/admin/StatCard';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Input, Textarea } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';
import { date, dateTime } from '@/lib/format';

interface Activation {
    id: number;
    domain: string;
    version: string | null;
    active: boolean;
    activated_at: string;
    last_checked_at: string | null;
}

interface LicenseRow {
    id: number;
    code: string;
    customer_name: string;
    customer_email: string | null;
    max_activations: number;
    updates_until: string | null;
    notes: string | null;
    revoked: boolean;
    revoked_reason: string | null;
    created_at: string;
    activations: Activation[];
}

interface Props {
    licenses: LicenseRow[];
    filters: { search: string };
    stats: { licenses: number; active_sites: number; revoked: number };
}

export default function LicensesIndex({ licenses, filters, stats }: Props) {
    const [editing, setEditing] = useState<LicenseRow | 'new' | null>(null);
    const [search, setSearch] = useState(filters.search);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('admin.licenses.index'), search ? { search } : {}, { preserveState: true });
    };

    return (
        <BackOfficeLayout
            title="Licenses sold"
            description="Purchase codes and the sites they run on"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New purchase code
                </Button>
            }
        >
            <Head title="Licenses" />

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <StatCard label="Purchase codes" value={stats.licenses} />
                <StatCard label="Sites running" value={stats.active_sites} tone="green" />
                <StatCard label="Cancelled" value={stats.revoked} tone={stats.revoked > 0 ? 'red' : 'default'} />
            </div>

            <form onSubmit={submitSearch} className="mb-4 flex gap-2">
                <label className="relative block min-w-0 flex-1">
                    <span className="sr-only">Search</span>
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Code, customer, email or domain"
                        className="h-10 w-full rounded-lg border border-ink-600 bg-ink-900 pr-3 pl-9 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                    />
                </label>
                <Button type="submit" variant="secondary">
                    Search
                </Button>
            </form>

            {licenses.length === 0 ? (
                <Card>
                    <EmptyState icon={<KeyRound className="size-8" />} title={filters.search ? 'Nothing matches' : 'No purchase codes yet'}>
                        {filters.search ? 'Try another search.' : 'Create one for each copy you sell and send it to the customer with the ZIP.'}
                    </EmptyState>
                </Card>
            ) : (
                <div className="space-y-3">
                    {licenses.map((license) => (
                        <LicenseCard key={license.id} license={license} onEdit={() => setEditing(license)} />
                    ))}
                </div>
            )}

            {editing && <LicenseForm key={editing === 'new' ? 'new' : editing.id} license={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </BackOfficeLayout>
    );
}

function LicenseCard({ license, onEdit }: { license: LicenseRow; onEdit: () => void }) {
    const active = license.activations.filter((activation) => activation.active);
    const [copied, setCopied] = useState(false);

    const copy = () => {
        navigator.clipboard?.writeText(license.code).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <Card className={cn(license.revoked && 'border-danger-600/40')}>
            <div className="flex flex-wrap items-start gap-3 p-4">
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-base font-bold tracking-wider text-strong">{license.code}</span>
                        <button type="button" onClick={copy} className="rounded p-1 text-ink-400 hover:text-strong" aria-label={`Copy ${license.code}`}>
                            <Copy className="size-3.5" aria-hidden />
                        </button>
                        {copied && <span className="text-xs text-go-400">Copied</span>}
                        {license.revoked ? <Badge tone="red">Cancelled</Badge> : <Badge tone={active.length > 0 ? 'green' : 'neutral'}>{active.length > 0 ? 'In use' : 'Not activated'}</Badge>}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-ink-100">
                        {license.customer_name}
                        {license.customer_email && <span className="font-normal text-ink-400"> · {license.customer_email}</span>}
                    </p>
                    <p className="text-xs text-ink-400">
                        Sold {dateTime(license.created_at)} · {active.length} of {license.max_activations} {license.max_activations === 1 ? 'domain' : 'domains'} in use ·{' '}
                        {license.updates_until ? `updates until ${date(license.updates_until)}` : 'updates for life'}
                    </p>
                    {license.revoked && license.revoked_reason && <p className="mt-1 text-xs text-danger-400">Reason: {license.revoked_reason}</p>}
                    {license.notes && <p className="mt-1 text-xs whitespace-pre-line text-ink-300">{license.notes}</p>}
                </div>
                <div className="flex shrink-0 flex-wrap gap-1.5">
                    <Button variant="ghost" size="xs" onClick={onEdit}>
                        Edit
                    </Button>
                    {license.revoked ? (
                        <Button variant="ghost" size="xs" onClick={() => router.post(route('admin.licenses.restore', license.id), {}, { preserveScroll: true })} icon={<RotateCcw className="size-3.5" aria-hidden />}>
                            Restore
                        </Button>
                    ) : (
                        <ConfirmButton
                            size="xs"
                            title={`Cancel ${license.code}?`}
                            message="Every site using it stops at its next check (within a day) and shows that it is unavailable. You can restore it later."
                            confirmLabel="Cancel license"
                            onConfirm={() => router.post(route('admin.licenses.revoke', license.id), { reason: 'Cancelled by the seller' }, { preserveScroll: true })}
                            icon={<Ban className="size-3.5" aria-hidden />}
                        >
                            Cancel
                        </ConfirmButton>
                    )}
                </div>
            </div>

            {license.activations.length > 0 && (
                <ul className="divide-y divide-ink-700 border-t border-ink-700">
                    {license.activations.map((activation) => (
                        <li key={activation.id} className={cn('flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm', !activation.active && 'opacity-60')}>
                            <Globe className={cn('size-4 shrink-0', activation.active ? 'text-go-400' : 'text-ink-500')} aria-hidden />
                            <a href={`https://${activation.domain}`} target="_blank" rel="noreferrer" className="min-w-0 truncate font-semibold text-ink-100 hover:text-flame-400">
                                {activation.domain}
                            </a>
                            <span className="text-xs text-ink-400">
                                {activation.active ? `last seen ${activation.last_checked_at ? dateTime(activation.last_checked_at) : 'never'}` : 'released'}
                                {activation.version && ` · v${activation.version}`}
                            </span>
                            {activation.active && (
                                <span className="ml-auto">
                                    <ConfirmButton
                                        size="xs"
                                        title={`Release ${activation.domain}?`}
                                        message="That site stops at its next check, and the code can be activated on another domain. Use this when a customer moved without releasing it."
                                        confirmLabel="Release"
                                        onConfirm={() => router.delete(route('admin.licenses.release', [license.id, activation.id]), { preserveScroll: true })}
                                    >
                                        Release
                                    </ConfirmButton>
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

function LicenseForm({ license, onClose }: { license: LicenseRow | null; onClose: () => void }) {
    const form = useForm({
        customer_name: license?.customer_name ?? '',
        customer_email: license?.customer_email ?? '',
        max_activations: String(license?.max_activations ?? 1),
        updates_until: license?.updates_until ?? '',
        notes: license?.notes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (license) {
            form.put(route('admin.licenses.update', license.id), options);
        } else {
            form.post(route('admin.licenses.store'), options);
        }
    };

    return (
        <Modal
            open
            onClose={onClose}
            title={license ? `Edit ${license.code}` : 'New purchase code'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="license-form" loading={form.processing}>
                        {license ? 'Save' : 'Create code'}
                    </Button>
                </>
            }
        >
            <form id="license-form" onSubmit={submit} className="space-y-4">
                <Input label="Customer or shop" required autoFocus value={form.data.customer_name} onChange={(event) => form.setData('customer_name', event.target.value)} error={form.errors.customer_name} />
                <Input label="Customer email" type="email" value={form.data.customer_email} onChange={(event) => form.setData('customer_email', event.target.value)} error={form.errors.customer_email} />
                <Input
                    label="Domains at the same time"
                    type="number"
                    min={1}
                    max={100}
                    required
                    wrapperClassName="max-w-48"
                    hint="Usually 1. Local addresses (localhost, *.test) never count."
                    value={form.data.max_activations}
                    onChange={(event) => form.setData('max_activations', event.target.value)}
                    error={form.errors.max_activations}
                />
                <Input
                    label="Updates until"
                    type="date"
                    wrapperClassName="max-w-56"
                    hint="Releases published after this day are not offered to this customer. Leave empty for updates for life."
                    value={form.data.updates_until}
                    onChange={(event) => form.setData('updates_until', event.target.value)}
                    error={form.errors.updates_until}
                />
                <Textarea label="Notes (only you see these)" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} error={form.errors.notes} />
            </form>
        </Modal>
    );
}
