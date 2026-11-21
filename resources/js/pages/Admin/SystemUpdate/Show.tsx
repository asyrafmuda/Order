import { Head, router } from '@inertiajs/react';
import { Archive, CheckCircle2, DownloadCloud, FileWarning, Lock, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { date, dateTime } from '@/lib/format';

interface Props {
    current: string;
    supported: boolean;
    blockers: string[];
    checkedAt: string | null;
    update: { version: string; notes: string | null; size: number; published_at: string | null } | null;
    locked: { version: string; updates_until: string | null } | null;
    plan: { to: string; changed: string[]; changed_count: number; files: number; baseline: boolean } | null;
    backups: { name: string; size: number; at: number }[];
}

const megabytes = (bytes: number) => `${(bytes / 1048576).toFixed(1)} MB`;

export default function SystemUpdateShow({ current, supported, blockers, checkedAt, update, locked, plan, backups }: Props) {
    const [busy, setBusy] = useState<'check' | 'install' | null>(null);

    const check = () => router.post(route('admin.system-update.check'), {}, { preserveScroll: true, onStart: () => setBusy('check'), onFinish: () => setBusy(null) });

    const install = (replaceChanged = false) =>
        router.post(route('admin.system-update.install'), { replace_changed: replaceChanged }, { preserveScroll: true, onStart: () => setBusy('install'), onFinish: () => setBusy(null) });

    return (
        <BackOfficeLayout title="System Update" description="New versions from the seller, installed with one click">
            <Head title="System Update" />

            <div className="mx-auto max-w-2xl space-y-5">
                <Card>
                    <div className="flex flex-wrap items-center gap-3 p-4">
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-semibold text-ink-400">This site runs version</p>
                            <p className="font-display text-3xl leading-none text-strong">{current}</p>
                            <p className="mt-1 text-xs text-ink-400">{checkedAt ? `Last checked ${dateTime(checkedAt)}` : 'Not checked yet'} · checked every night by itself</p>
                        </div>
                        <Button variant="secondary" onClick={check} loading={busy === 'check'} disabled={!supported || busy !== null} icon={<RefreshCw className="size-4" aria-hidden />}>
                            Check for updates
                        </Button>
                    </div>
                </Card>

                {!supported && (
                    <Notice icon={<Lock className="size-5" />}>Updates come with an activated license. Activate this copy in Admin → License first.</Notice>
                )}

                {blockers.map((blocker) => (
                    <Notice key={blocker} tone="danger" icon={<TriangleAlert className="size-5" />}>
                        {blocker}
                    </Notice>
                ))}

                {supported && !update && !locked && checkedAt && (
                    <Notice tone="go" icon={<CheckCircle2 className="size-5" />}>
                        This site is up to date.
                    </Notice>
                )}

                {locked && !update && (
                    <Notice icon={<Lock className="size-5" />}>
                        Version {locked.version} is out, but the update period of this license ended{locked.updates_until ? ` on ${date(locked.updates_until)}` : ''}. Contact the seller to renew it.
                    </Notice>
                )}

                {update && (
                    <Card className="border-flame-500/50">
                        <CardHeader title={`Version ${update.version} is available`} icon={<DownloadCloud className="size-4" />} action={<Badge tone="flame">New</Badge>} />
                        <div className="space-y-4 p-4">
                            <p className="text-xs text-ink-400">
                                {update.published_at && `Released ${date(update.published_at)} · `}
                                {megabytes(update.size)}
                            </p>
                            {update.notes ? (
                                <div>
                                    <p className="mb-1 text-xs font-semibold text-ink-300">What&apos;s new</p>
                                    <p className="text-sm whitespace-pre-line text-ink-100">{update.notes}</p>
                                </div>
                            ) : null}
                            {locked && <p className="text-xs text-ink-400">Version {locked.version} is also out, but your update period does not cover it.</p>}

                            {plan && plan.changed_count > 0 ? (
                                <div className="space-y-3 rounded-lg border border-danger-600/50 bg-danger-900/30 p-3">
                                    <p className="flex items-start gap-2 text-sm font-semibold text-danger-300">
                                        <FileWarning className="mt-0.5 size-4 shrink-0" aria-hidden />
                                        {plan.changed_count === 1 ? 'One file was' : `${plan.changed_count} files were`} changed on this site, and the update replaces or removes {plan.changed_count === 1 ? 'it' : 'them'}:
                                    </p>
                                    <ul className="max-h-48 space-y-0.5 overflow-y-auto rounded bg-ink-900 p-2 font-mono text-xs text-ink-200">
                                        {plan.changed.map((file) => (
                                            <li key={file} className="truncate">
                                                {file}
                                            </li>
                                        ))}
                                        {plan.changed_count > plan.changed.length && <li>… and {plan.changed_count - plan.changed.length} more</li>}
                                    </ul>
                                    <p className="text-xs text-ink-300">
                                        Your versions are saved in a backup (listed below) before they are replaced, so whoever made the changes can add them again. If you are not sure, ask them first.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="danger" onClick={() => install(true)} loading={busy === 'install'} disabled={busy !== null}>
                                            Replace them and update
                                        </Button>
                                        <Button variant="ghost" onClick={() => router.delete(route('admin.system-update.cancel'), { preserveScroll: true })} disabled={busy !== null}>
                                            Not now
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <ul className="list-disc space-y-1 pl-5 text-xs text-ink-300">
                                        <li>The site shows &ldquo;Updating…&rdquo; to customers for about a minute. Pick a quiet time.</li>
                                        <li>Every file that is replaced is backed up first. If anything goes wrong, the site is put back as it was.</li>
                                        <li>Settings, orders, menu and uploaded pictures are not touched. Backing up the database first is still wise (cPanel → phpMyAdmin → Export).</li>
                                        {plan && !plan.baseline && <li>This version has no file list yet, so changes made to the code here cannot be detected this time.</li>}
                                    </ul>
                                    <Button onClick={() => install(false)} loading={busy === 'install'} disabled={blockers.length > 0 || busy !== null} icon={<DownloadCloud className="size-4" aria-hidden />}>
                                        {busy === 'install' ? 'Downloading and installing…' : `Update to ${update.version}`}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </Card>
                )}

                {backups.length > 0 && (
                    <Card>
                        <CardHeader title="Backups of replaced files" icon={<Archive className="size-4" />} />
                        <ul className="divide-y divide-ink-700">
                            {backups.map((backup) => (
                                <li key={backup.name} className="flex flex-wrap items-center gap-x-3 px-4 py-2.5 text-sm">
                                    <span className="min-w-0 flex-1 truncate font-mono text-xs text-ink-100">{backup.name}</span>
                                    <span className="text-xs text-ink-400">
                                        {dateTime(new Date(backup.at * 1000).toISOString())} · {megabytes(backup.size)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="border-t border-ink-700 px-4 py-2.5 text-xs text-ink-400">In storage/app/updates/backups on the server (File Manager). The newest three are kept.</p>
                    </Card>
                )}
            </div>
        </BackOfficeLayout>
    );
}

function Notice({ icon, tone = 'neutral', children }: { icon: ReactNode; tone?: 'neutral' | 'danger' | 'go'; children: ReactNode }) {
    const tones = {
        neutral: 'border-ink-700 bg-ink-850 text-ink-200',
        danger: 'border-danger-600/50 bg-danger-900/30 text-danger-300',
        go: 'border-go-500/40 bg-go-900 text-go-300',
    };

    return (
        <div className={`flex items-start gap-3 rounded-xl border p-4 text-sm ${tones[tone]}`}>
            <span className="mt-0.5 shrink-0">{icon}</span>
            <p>{children}</p>
        </div>
    );
}
