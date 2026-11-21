import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Check, Copy, CreditCard, FlaskConical, Plug } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input, Toggle } from '@/components/ui/Form';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { cn } from '@/lib/cn';

type GatewayName = 'none' | 'billplz' | 'bayarcash' | 'stripe' | 'fake';

interface Props {
    active: GatewayName;
    ready: boolean;
    /** The demo gateway cannot be picked on a live site. */
    production: boolean;
    saved: {
        billplz: { api_key: boolean; collection_id: string; sandbox: boolean };
        bayarcash: { token: boolean; secret_key: boolean; portal_key: string; sandbox: boolean };
        stripe: { secret_key: boolean };
    };
    urls: { stripe_webhook: string };
}

const choices: { name: GatewayName; title: string; blurb: string }[] = [
    { name: 'none', title: 'None', blurb: 'Customers pay at the counter only.' },
    { name: 'billplz', title: 'Billplz', blurb: 'FPX online banking, cards and e-wallets. Malaysian.' },
    { name: 'bayarcash', title: 'Bayarcash', blurb: 'FPX, DuitNow QR and cards. Malaysian.' },
    { name: 'stripe', title: 'Stripe', blurb: 'Cards, FPX and GrabPay. International.' },
    { name: 'fake', title: 'Demo', blurb: 'Pretend payments for trying the system. No money moves.' },
];

/** Placeholder for a secret that is already saved: it is never sent back to the browser. */
const keepSaved = 'Saved — leave empty to keep it';

export default function PaymentGatewayEdit({ active, ready, production, saved, urls }: Props) {
    const form = useForm({
        gateway: active,
        billplz: { api_key: '', collection_id: saved.billplz.collection_id, sandbox: saved.billplz.sandbox },
        bayarcash: { token: '', secret_key: '', portal_key: saved.bayarcash.portal_key, sandbox: saved.bayarcash.sandbox },
        stripe: { secret_key: '' },
    });
    const { data, errors } = form;
    const [testing, setTesting] = useState(false);

    // Change one field of one gateway's details.
    const set = <G extends 'billplz' | 'bayarcash' | 'stripe'>(gateway: G, field: keyof (typeof data)[G], value: string | boolean) =>
        form.setData((current) => ({ ...current, [gateway]: { ...current[gateway], [field]: value } }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // Send only the chosen gateway's details; typed secrets are cleared after
        // saving, as the page only ever shows that one is saved.
        form.transform((current) => {
            const { gateway } = current;

            return gateway === 'billplz' || gateway === 'bayarcash' || gateway === 'stripe' ? { gateway, [gateway]: current[gateway] } : { gateway };
        });
        form.put(route('admin.payment-gateway.update'), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData((current) => ({
                    ...current,
                    billplz: { ...current.billplz, api_key: '' },
                    bayarcash: { ...current.bayarcash, token: '', secret_key: '' },
                    stripe: { secret_key: '' },
                }));
            },
        });
    };

    const test = () => router.post(route('admin.payment-gateway.test'), {}, { preserveScroll: true, onStart: () => setTesting(true), onFinish: () => setTesting(false) });

    const shown = choices.filter((choice) => choice.name !== 'fake' || !production);
    const unsaved = data.gateway !== active;

    return (
        <BackOfficeLayout title="Payment Gateway" description="How customers pay online for FPX, card and e-wallet orders">
            <Head title="Payment Gateway" />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
                <Card>
                    <CardHeader
                        title="Gateway"
                        icon={<CreditCard className="size-4" />}
                        action={
                            active === 'none' ? (
                                <Badge tone="neutral">Off</Badge>
                            ) : ready ? (
                                <Badge tone="green">{active === 'fake' ? 'Demo on' : 'Taking payments'}</Badge>
                            ) : (
                                <Badge tone="red">Not ready</Badge>
                            )
                        }
                    />
                    <div className="space-y-3 p-4">
                        <p className="text-sm text-ink-300">Pick one. Money goes straight from the customer to your account at that gateway; this system never sees card or bank details.</p>
                        <div role="radiogroup" aria-label="Payment gateway" className="grid gap-2 sm:grid-cols-2">
                            {shown.map((choice) => (
                                <button
                                    key={choice.name}
                                    type="button"
                                    role="radio"
                                    aria-checked={data.gateway === choice.name}
                                    onClick={() => form.setData('gateway', choice.name)}
                                    className={cn(
                                        'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors',
                                        data.gateway === choice.name ? 'border-flame-400 bg-flame-900/40' : 'border-ink-700 bg-ink-900 hover:border-ink-500',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border',
                                            data.gateway === choice.name ? 'border-flame-400 bg-flame-500 text-white' : 'border-ink-500',
                                        )}
                                    >
                                        {data.gateway === choice.name && <Check className="size-3.5" aria-hidden />}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="flex items-center gap-2 text-sm font-bold text-strong">
                                            {choice.name === 'none' && <Ban className="size-3.5 text-ink-400" aria-hidden />}
                                            {choice.name === 'fake' && <FlaskConical className="size-3.5 text-ink-400" aria-hidden />}
                                            {choice.title}
                                            {choice.name === active && <span className="text-xs font-semibold text-go-400">current</span>}
                                        </span>
                                        <span className="block text-xs text-ink-400">{choice.blurb}</span>
                                    </span>
                                </button>
                            ))}
                        </div>
                        {errors.gateway && (
                            <p className="text-xs font-medium text-danger-400" role="alert">
                                {errors.gateway}
                            </p>
                        )}
                    </div>
                </Card>

                {data.gateway === 'billplz' && (
                    <Card>
                        <CardHeader title="Billplz details" icon={<Plug className="size-4" />} />
                        <div className="space-y-4 p-4">
                            <Steps>
                                <li>Sign in to Billplz, open <b>Settings → Keys &amp; Integration</b> and copy the <b>API Secret Key</b>.</li>
                                <li>Open <b>Billing → Collections</b>, create a collection (or use one you have) and copy its <b>ID</b>.</li>
                            </Steps>
                            <Input
                                label="API secret key"
                                type="password"
                                autoComplete="off"
                                required={!saved.billplz.api_key}
                                placeholder={saved.billplz.api_key ? keepSaved : ''}
                                value={data.billplz.api_key}
                                onChange={(event) => set('billplz', 'api_key', event.target.value)}
                                error={errors['billplz.api_key']}
                            />
                            <Input
                                label="Collection ID"
                                required
                                wrapperClassName="sm:max-w-xs"
                                value={data.billplz.collection_id}
                                onChange={(event) => set('billplz', 'collection_id', event.target.value)}
                                error={errors['billplz.collection_id']}
                            />
                            <Toggle
                                checked={data.billplz.sandbox}
                                onChange={(checked) => set('billplz', 'sandbox', checked)}
                                label="Sandbox (testing)"
                                description="Uses billplz-sandbox.com and its own keys. Switch off when you go live."
                            />
                        </div>
                    </Card>
                )}

                {data.gateway === 'bayarcash' && (
                    <Card>
                        <CardHeader title="Bayarcash details" icon={<Plug className="size-4" />} />
                        <div className="space-y-4 p-4">
                            <Steps>
                                <li>Sign in to the Bayarcash console and open <b>API Keys</b>: copy the <b>Personal Access Token</b> and the <b>API Secret Key</b>.</li>
                                <li>Open <b>Portals</b> and copy the <b>portal key</b> of the portal this shop uses.</li>
                                <li>
                                    At checkout, <b>FPX</b> opens FPX, <b>e-wallet</b> opens DuitNow QR and <b>card</b> opens credit card. Switch on in the portal only the channels you also switch on in Settings.
                                </li>
                            </Steps>
                            <Input
                                label="Personal access token"
                                type="password"
                                autoComplete="off"
                                required={!saved.bayarcash.token}
                                placeholder={saved.bayarcash.token ? keepSaved : ''}
                                value={data.bayarcash.token}
                                onChange={(event) => set('bayarcash', 'token', event.target.value)}
                                error={errors['bayarcash.token']}
                            />
                            <Input
                                label="API secret key"
                                type="password"
                                autoComplete="off"
                                hint="Recommended: payment requests are then signed so nobody can change the amount on the way."
                                placeholder={saved.bayarcash.secret_key ? keepSaved : ''}
                                value={data.bayarcash.secret_key}
                                onChange={(event) => set('bayarcash', 'secret_key', event.target.value)}
                                error={errors['bayarcash.secret_key']}
                            />
                            <Input
                                label="Portal key"
                                required
                                value={data.bayarcash.portal_key}
                                onChange={(event) => set('bayarcash', 'portal_key', event.target.value)}
                                error={errors['bayarcash.portal_key']}
                            />
                            <Toggle
                                checked={data.bayarcash.sandbox}
                                onChange={(checked) => set('bayarcash', 'sandbox', checked)}
                                label="Sandbox (testing)"
                                description="Uses the Bayarcash sandbox console and its own keys. Switch off when you go live."
                            />
                        </div>
                    </Card>
                )}

                {data.gateway === 'stripe' && (
                    <Card>
                        <CardHeader title="Stripe details" icon={<Plug className="size-4" />} />
                        <div className="space-y-4 p-4">
                            <Steps>
                                <li>
                                    In the Stripe dashboard open <b>Developers → API keys</b> and copy the <b>Secret key</b> (sk_live_…). Use the sk_test_… key while testing.
                                </li>
                                <li>
                                    Under <b>Settings → Payment methods</b>, switch on what you accept (cards, FPX, GrabPay). The account must be able to take Malaysian ringgit.
                                </li>
                            </Steps>
                            <Input
                                label="Secret key"
                                type="password"
                                autoComplete="off"
                                required={!saved.stripe.secret_key}
                                placeholder={saved.stripe.secret_key ? keepSaved : 'sk_live_…'}
                                value={data.stripe.secret_key}
                                onChange={(event) => set('stripe', 'secret_key', event.target.value)}
                                error={errors['stripe.secret_key']}
                            />
                            <WebhookUrl url={urls.stripe_webhook}>
                                Optional but recommended: in <b>Developers → Webhooks</b> add this endpoint for the <b>checkout.session.completed</b> event, so an order is confirmed even when the customer closes the
                                page before returning.
                            </WebhookUrl>
                        </div>
                    </Card>
                )}

                {data.gateway === 'fake' && (
                    <p className="rounded-lg border border-flame-500/40 bg-flame-900 px-3 py-2 text-sm text-flame-300">
                        Demo only: every &ldquo;payment&rdquo; is approved or declined by a button, and no money moves. Pick a real gateway before the shop opens.
                    </p>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    {active !== 'none' && active !== 'fake' && (
                        <Button variant="secondary" onClick={test} loading={testing} disabled={unsaved}>
                            Test connection
                        </Button>
                    )}
                    <span className="ml-auto text-xs text-ink-400">{unsaved ? 'Not saved yet' : 'Saving checks the connection right away.'}</span>
                    <Button type="submit" loading={form.processing}>
                        Save
                    </Button>
                </div>
            </form>
        </BackOfficeLayout>
    );
}

function Steps({ children }: { children: ReactNode }) {
    return <ol className="list-decimal space-y-1 rounded-lg border border-ink-700 bg-ink-900 py-3 pr-3 pl-8 text-sm text-ink-300 [&_b]:text-ink-100">{children}</ol>;
}

function WebhookUrl({ url, children }: { url: string; children: ReactNode }) {
    const [copied, setCopied] = useState(false);

    const copy = () =>
        navigator.clipboard?.writeText(url).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });

    return (
        <div className="space-y-1.5">
            <p className="text-xs font-semibold text-ink-300">Webhook URL</p>
            <div className="flex items-center gap-2 rounded-lg border border-ink-600 bg-ink-900 px-3 py-2">
                <code className="min-w-0 flex-1 truncate text-xs text-ink-100">{url}</code>
                <button type="button" onClick={copy} className="shrink-0 rounded p-1 text-ink-400 hover:text-strong" aria-label="Copy webhook URL">
                    {copied ? <Check className="size-3.5 text-go-400" aria-hidden /> : <Copy className="size-3.5" aria-hidden />}
                </button>
            </div>
            <p className="text-xs text-ink-400 [&_b]:text-ink-200">{children}</p>
        </div>
    );
}
