import { Head, router, usePage } from '@inertiajs/react';
import { FlaskConical, Lock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { money } from '@/lib/format';

interface FakePaymentProps {
    payment: {
        reference: string;
        amount: number;
        method: string;
        method_label: string;
        order_number: string;
        order_uuid: string;
    };
}

/** Simulated bank page of the "fake" payment gateway. Never shown when a real gateway is configured. */
export default function FakePayment({ payment }: FakePaymentProps) {
    const { shop } = usePage().props;
    const [busy, setBusy] = useState<'approve' | 'decline' | null>(null);

    const respond = (action: 'approve' | 'decline') => {
        setBusy(action);
        router.post(route('payments.fake.complete', payment.reference), { action }, { onFinish: () => setBusy(null) });
    };

    return (
        <div className="flex min-h-dvh items-center justify-center bg-slate-100 px-4 py-10 text-slate-900">
            <Head title="Secure payment" />
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
                <div className="flex items-center gap-2 bg-amber-100 px-5 py-2.5 text-xs font-semibold text-amber-900">
                    <FlaskConical className="size-4" aria-hidden />
                    Test mode — no real money moves. This page simulates the bank.
                </div>
                <div className="space-y-6 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{payment.method_label}</p>
                            <p className="text-lg font-bold">{shop.name} · Order #{payment.order_number}</p>
                        </div>
                        <Lock className="size-5 text-emerald-600" aria-hidden />
                    </div>

                    <div className="rounded-xl bg-slate-50 p-5 text-center ring-1 ring-slate-200">
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Amount to pay</p>
                        <p className="mt-1 text-4xl font-extrabold tracking-tight">{money(payment.amount)}</p>
                        <p className="mt-2 font-mono text-xs text-slate-500">Ref {payment.reference}</p>
                    </div>

                    <div className="space-y-2">
                        <Button size="lg" variant="success" className="w-full" loading={busy === 'approve'} disabled={busy !== null} onClick={() => respond('approve')}>
                            Approve payment
                        </Button>
                        <button
                            type="button"
                            disabled={busy !== null}
                            onClick={() => respond('decline')}
                            className="h-11 w-full rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            {busy === 'decline' ? 'Declining…' : 'Decline / cancel'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
