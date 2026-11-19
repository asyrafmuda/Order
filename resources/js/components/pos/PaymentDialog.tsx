import { Banknote, Clock3, CreditCard, QrCode, Send, type LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Modal } from '@/components/ui/Modal';
import { cn } from '@/lib/cn';
import { inputToSen, money } from '@/lib/format';
import { cashSuggestions } from '@/lib/pos';

export type PosPayment = 'cash' | 'card' | 'ewallet' | 'later';

export interface PaymentResult {
    payment: PosPayment;
    tendered: number | null;
    phone: string;
    notes: string;
}

interface PaymentDialogProps {
    total: number;
    processing: boolean;
    errors: Record<string, string | undefined>;
    onConfirm: (result: PaymentResult) => void;
    onClose: () => void;
}

const methods: { value: PosPayment; label: string; hint: string; icon: LucideIcon }[] = [
    { value: 'cash', label: 'Cash', hint: 'Count the change', icon: Banknote },
    { value: 'card', label: 'Card', hint: 'Card terminal', icon: CreditCard },
    { value: 'ewallet', label: 'QR / E-wallet', hint: 'DuitNow, TnG…', icon: QrCode },
    { value: 'later', label: 'Pay later', hint: 'Collect at hand-over', icon: Clock3 },
];

/** Take payment for the ticket. Cash shows the change before the order is sent. */
export function PaymentDialog({ total, processing, errors, onConfirm, onClose }: PaymentDialogProps) {
    const [payment, setPayment] = useState<PosPayment>('cash');
    const [tendered, setTendered] = useState<number | null>(null);
    const [typed, setTyped] = useState('');
    const [phone, setPhone] = useState('');
    const [notes, setNotes] = useState('');

    const received = typed !== '' ? inputToSen(typed) : tendered;
    const short = payment === 'cash' && received !== null && received < total;
    const change = payment === 'cash' && received !== null && received >= total ? received - total : null;

    const confirm = () => {
        if (!processing && !short) {
            onConfirm({ payment, tendered: payment === 'cash' ? received : null, phone: phone.trim(), notes: notes.trim() });
        }
    };

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirm();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    });

    const amount = (active: boolean) =>
        cn('h-12 rounded-xl border px-4 text-sm font-extrabold transition-colors', active ? 'border-flame-400 bg-flame-400 text-ink-950' : 'border-ink-600 bg-ink-900 text-ink-100 hover:border-ink-400');

    return (
        <Modal
            open
            onClose={onClose}
            title="Take payment"
            footer={
                <button
                    type="button"
                    onClick={confirm}
                    disabled={processing || short}
                    className="flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-go-500 text-base font-extrabold text-ink-950 hover:bg-go-400 disabled:cursor-not-allowed disabled:bg-ink-700 disabled:text-ink-400"
                >
                    <Send className="size-5" aria-hidden />
                    {processing ? 'Sending…' : payment === 'later' ? 'Send to kitchen · collect later' : 'Paid · send to kitchen'}
                    <kbd className="ml-1 hidden rounded bg-ink-950/20 px-1.5 py-0.5 text-[11px] font-bold sm:inline">Enter</kbd>
                </button>
            }
        >
            <div className="space-y-5">
                <div className="flex items-end justify-between rounded-xl bg-ink-900 px-4 py-3">
                    <span className="text-xs font-extrabold tracking-[0.15em] text-ink-400 uppercase">Total due</span>
                    <span className="font-display text-6xl leading-none text-flame-400">{money(total)}</span>
                </div>

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4" role="radiogroup" aria-label="Payment method">
                    {methods.map((method) => (
                        <button
                            key={method.value}
                            type="button"
                            role="radio"
                            aria-checked={payment === method.value}
                            onClick={() => setPayment(method.value)}
                            className={cn(
                                'flex h-20 flex-col items-center justify-center gap-1 rounded-xl border text-center transition-colors',
                                payment === method.value ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-900 text-ink-200 hover:border-ink-400',
                            )}
                        >
                            <method.icon className="size-5" aria-hidden />
                            <span className="text-sm leading-none font-extrabold">{method.label}</span>
                            <span className={cn('text-[10px]', payment === method.value ? 'text-white/80' : 'text-ink-500')}>{method.hint}</span>
                        </button>
                    ))}
                </div>

                {payment === 'cash' && (
                    <div className="space-y-3">
                        <p className="text-xs font-extrabold tracking-[0.12em] text-ink-200 uppercase">Cash received</p>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" className={amount(typed === '' && tendered === total)} onClick={() => (setTyped(''), setTendered(total))}>
                                Exact
                            </button>
                            {cashSuggestions(total).map((value) => (
                                <button key={value} type="button" className={amount(typed === '' && tendered === value)} onClick={() => (setTyped(''), setTendered(value))}>
                                    {money(value)}
                                </button>
                            ))}
                            <label className="relative">
                                <span className="sr-only">Other amount in ringgit</span>
                                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-ink-400">RM</span>
                                <input
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={typed}
                                    onChange={(event) => setTyped(event.target.value)}
                                    placeholder="Other"
                                    className="h-12 w-32 rounded-xl border border-ink-600 bg-ink-900 pr-3 pl-11 text-sm font-bold text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                                />
                            </label>
                        </div>
                        <div className={cn('flex items-end justify-between rounded-xl px-4 py-3', short ? 'bg-brand-900' : 'bg-go-900')} aria-live="polite">
                            <span className={cn('text-xs font-extrabold tracking-[0.15em] uppercase', short ? 'text-brand-400' : 'text-go-400')}>{short ? 'Still short by' : 'Change'}</span>
                            <span className={cn('font-display text-5xl leading-none', short ? 'text-brand-400' : 'text-white')}>
                                {short ? money(total - (received ?? 0)) : change !== null ? money(change) : '—'}
                            </span>
                        </div>
                        {errors.tendered && <p className="text-xs font-medium text-brand-400">{errors.tendered}</p>}
                    </div>
                )}

                <details className="rounded-xl border border-ink-700 px-4 py-3 text-sm">
                    <summary className="cursor-pointer font-semibold text-ink-300">Phone for updates, order note (optional)</summary>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold text-ink-300">Mobile number</span>
                            <input type="tel" value={phone} onChange={(event) => setPhone(event.target.value)} placeholder="012-345 6789" className="h-10 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none" />
                            {errors.customer_phone && <span className="mt-1 block text-xs font-medium text-brand-400">{errors.customer_phone}</span>}
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold text-ink-300">Note for the whole order</span>
                            <input type="text" value={notes} maxLength={500} onChange={(event) => setNotes(event.target.value)} placeholder="e.g. Pack separately" className="h-10 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none" />
                        </label>
                    </div>
                </details>

                {errors.lines && <p className="rounded-lg bg-brand-900 px-3 py-2 text-sm font-medium text-brand-400">{errors.lines}</p>}
            </div>
        </Modal>
    );
}
