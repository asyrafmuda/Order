import { Head, router, useForm } from '@inertiajs/react';
import { PackagePlus, Pencil, Trash2, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, EmptyState } from '@/components/ui/Card';
import { ConfirmButton } from '@/components/ui/ConfirmButton';
import { Checkbox, Field, Textarea, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { date, dateTime } from '@/lib/format';

interface ReleaseRow {
    id: number;
    version: string;
    notes: string | null;
    size: number;
    audience: 'all' | 'selected';
    license_ids: number[];
    published_at: string | null;
    created_at: string;
    sites: number;
}

interface LicenseOption {
    id: number;
    label: string;
    updates_until: string | null;
    revoked: boolean;
}

interface Props {
    releases: ReleaseRow[];
    licenses: LicenseOption[];
    versions: Record<string, number>;
    uploadLimit: string;
    /** 0 when PHP sets no limit. */
    uploadLimitBytes: number;
}

const megabytes = (bytes: number) => `${(bytes / 1048576).toFixed(1)} MB`;

export default function ReleasesIndex({ releases, licenses, versions, uploadLimit, uploadLimitBytes }: Props) {
    const [editing, setEditing] = useState<ReleaseRow | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Releases"
            description="Updates your customers install with one click"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<PackagePlus className="size-4" aria-hidden />}>
                    Upload update
                </Button>
            }
        >
            <Head title="Releases" />

            <div className="space-y-5">
                <Card className="p-4">
                    <p className="text-sm text-ink-300">
                        Bump <code className="text-ink-100">VERSION</code>, build with <code className="text-ink-100">scripts/package.sh</code>, and upload the ZIP here. Published updates appear in each
                        customer&apos;s Admin → System Update within a day.
                    </p>
                    {Object.keys(versions).length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {Object.entries(versions).map(([version, count]) => (
                                <span key={version} className="rounded-lg border border-ink-700 bg-ink-900 px-2.5 py-1 text-xs text-ink-300">
                                    <span className="font-bold text-strong">{version === 'unknown' ? 'Unknown' : `v${version}`}</span> · {count} {count === 1 ? 'site' : 'sites'}
                                </span>
                            ))}
                        </div>
                    )}
                </Card>

                {releases.length === 0 ? (
                    <Card>
                        <EmptyState icon={<Upload className="size-8" />} title="No updates uploaded yet">
                            Upload the ZIP of a new version to offer it to your customers.
                        </EmptyState>
                    </Card>
                ) : (
                    releases.map((release) => (
                        <Card key={release.id}>
                            <CardHeader
                                title={`Version ${release.version}`}
                                action={
                                    <div className="flex items-center gap-1.5">
                                        {release.published_at ? <Badge tone="green">Published</Badge> : <Badge tone="neutral">Draft</Badge>}
                                        {release.audience === 'selected' && <Badge tone="blue">{release.license_ids.length} selected</Badge>}
                                    </div>
                                }
                            />
                            <div className="space-y-2 p-4">
                                <p className="text-xs text-ink-400">
                                    Uploaded {dateTime(release.created_at)} · {megabytes(release.size)}
                                    {release.published_at && ` · published ${date(release.published_at)}`} · {release.sites} {release.sites === 1 ? 'site runs it' : 'sites run it'}
                                </p>
                                {release.audience === 'selected' && (
                                    <p className="text-xs text-ink-300">
                                        For: {licenses.filter((license) => release.license_ids.includes(license.id)).map((license) => license.label).join(', ') || '—'}
                                    </p>
                                )}
                                {release.notes && <p className="text-sm whitespace-pre-line text-ink-100">{release.notes}</p>}
                                <div className="flex flex-wrap gap-1.5 pt-1">
                                    <Button variant="ghost" size="xs" onClick={() => setEditing(release)} icon={<Pencil className="size-3.5" aria-hidden />}>
                                        Edit
                                    </Button>
                                    <ConfirmButton
                                        size="xs"
                                        title={`Delete version ${release.version}?`}
                                        message="Customers can no longer install it. Sites that already did keep it."
                                        confirmLabel="Delete"
                                        onConfirm={() => router.delete(route('admin.releases.destroy', release.id), { preserveScroll: true })}
                                        icon={<Trash2 className="size-3.5" aria-hidden />}
                                    >
                                        Delete
                                    </ConfirmButton>
                                </div>
                            </div>
                        </Card>
                    ))
                )}
            </div>

            {editing && <ReleaseForm
                    key={editing === 'new' ? 'new' : editing.id}
                    release={editing === 'new' ? null : editing}
                    licenses={licenses}
                    uploadLimit={uploadLimit}
                    uploadLimitBytes={uploadLimitBytes}
                    onClose={() => setEditing(null)}
                />}
        </BackOfficeLayout>
    );
}

function ReleaseForm({
    release,
    licenses,
    uploadLimit,
    uploadLimitBytes,
    onClose,
}: {
    release: ReleaseRow | null;
    licenses: LicenseOption[];
    uploadLimit: string;
    uploadLimitBytes: number;
    onClose: () => void;
}) {
    const form = useForm<{ file: File | null; notes: string; publish: boolean; audience: 'all' | 'selected'; license_ids: number[] }>({
        file: null,
        notes: release?.notes ?? '',
        publish: release ? release.published_at !== null : true,
        audience: release?.audience ?? 'all',
        license_ids: release?.license_ids ?? [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        // PHP throws away a bigger upload before the app sees it, so check here.
        if (!release && form.data.file && uploadLimitBytes > 0 && form.data.file.size > uploadLimitBytes) {
            form.setError('file', `This ZIP is ${megabytes(form.data.file.size)}, more than this server accepts (${uploadLimit}). Raise both limits in the PHP settings, or upload it on the server with: php artisan release:publish path/to/file.zip`);

            return;
        }

        if (release) {
            form.transform(({ file, ...data }) => data); // eslint-disable-line @typescript-eslint/no-unused-vars
            form.put(route('admin.releases.update', release.id), options);
        } else {
            form.post(route('admin.releases.store'), { ...options, forceFormData: true });
        }
    };

    const toggleLicense = (id: number, checked: boolean) =>
        form.setData('license_ids', checked ? [...form.data.license_ids, id] : form.data.license_ids.filter((current) => current !== id));

    return (
        <Modal
            open
            onClose={onClose}
            title={release ? `Version ${release.version}` : 'Upload an update'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="release-form" loading={form.processing}>
                        {release ? 'Save' : form.data.publish ? 'Upload and publish' : 'Upload as draft'}
                    </Button>
                </>
            }
        >
            <form id="release-form" onSubmit={submit} className="space-y-4">
                {!release && (
                    <Field label="ZIP from scripts/package.sh" required error={form.errors.file} hint={`This server accepts uploads up to: ${uploadLimit}. Bigger? Run php artisan release:publish on the server instead.`}>
                        {(id) => (
                            <input
                                id={id}
                                type="file"
                                accept=".zip,application/zip"
                                required
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-ink-200 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink-100"
                            />
                        )}
                    </Field>
                )}
                {form.progress && <progress value={form.progress.percentage} max={100} className="w-full" />}
                <Textarea label="What's new (customers see this)" rows={5} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} error={form.errors.notes} />

                <fieldset className="space-y-2">
                    <legend className="mb-1 text-xs font-semibold text-ink-300">Who gets it</legend>
                    <label className="flex items-center gap-2 text-sm text-ink-100">
                        <input type="radio" name="audience" checked={form.data.audience === 'all'} onChange={() => form.setData('audience', 'all')} /> Every customer
                    </label>
                    <label className="flex items-center gap-2 text-sm text-ink-100">
                        <input type="radio" name="audience" checked={form.data.audience === 'selected'} onChange={() => form.setData('audience', 'selected')} /> Only the customers I pick
                    </label>
                    {form.data.audience === 'selected' && (
                        <div className="max-h-48 space-y-1.5 overflow-y-auto rounded-lg border border-ink-700 bg-ink-900 p-3">
                            {licenses.length === 0 && <p className="text-xs text-ink-400">No purchase codes yet.</p>}
                            {licenses.map((license) => (
                                <Checkbox
                                    key={license.id}
                                    label={`${license.label}${license.revoked ? ' · cancelled' : ''}${license.updates_until ? ` · updates until ${date(license.updates_until)}` : ''}`}
                                    checked={form.data.license_ids.includes(license.id)}
                                    onChange={(event) => toggleLicense(license.id, event.target.checked)}
                                />
                            ))}
                        </div>
                    )}
                    {(form.errors.license_ids || form.errors.audience) && <p className="text-xs font-medium text-danger-400">{form.errors.license_ids || form.errors.audience}</p>}
                </fieldset>

                <Toggle
                    checked={form.data.publish}
                    onChange={(checked) => form.setData('publish', checked)}
                    label="Published"
                    description="Off = a draft nobody sees. Test it on your demo site first by picking only the demo's purchase code."
                />
            </form>
        </Modal>
    );
}
