import { Link } from '@inertiajs/react';
import { CircleCheckBig, Minus, Pencil, Plus, ReceiptText, ShoppingBag, Trash2, UtensilsCrossed, X } from 'lucide-react';
import { PrintReceiptButton } from '@/components/staff/PrintReceiptButton';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import { describe, unitPrice, type PosLine } from '@/lib/pos';
import type { ComboOffer, PosReceipt } from '@/types';

export interface PosTotals {
    count: number;
    subtotal: number;
    tax: number;
    total: number;
}

interface RecentOrder {
    uuid: string;
    order_number: string;
    customer_name: string;
    total: number;
    status_label: string;
    is_paid: boolean;
}

interface PosTicketProps {
    lines: PosLine[];
    combo: ComboOffer | null;
    totals: PosTotals;
    tax: { rate: number; inclusive: boolean };
    type: 'pickup' | 'dine_in';
    customerName: string;
    tables: { id: number; name: string; seats: number | null }[];
    tableId: number | null;
    onTable: (id: number | null) => void;
    receipt: PosReceipt | null;
    recent: RecentOrder[];
    onType: (type: 'pickup' | 'dine_in') => void;
    onCustomerName: (name: string) => void;
    onQuantity: (key: string, quantity: number) => void;
    onEdit: (line: PosLine) => void;
    onRemove: (key: string) => void;
    onClear: () => void;
    onCharge: () => void;
    onDismissReceipt: () => void;
}

/** The order being built: lines with one-tap quantity changes, totals and the Charge button. */
export function PosTicket(props: PosTicketProps) {
    const { lines, combo, totals, tax, type, customerName, tables, tableId, receipt, recent } = props;

    return (
        <aside className="flex min-h-0 flex-1 flex-col border-ink-700 bg-ink-900 md:border-l" aria-label="Current order">
            <div className="space-y-2 border-b border-ink-700 p-3">
                <div className="grid grid-cols-2 gap-1 rounded-xl bg-ink-850 p-1" role="radiogroup" aria-label="Order type">
                    {(
                        [
                            ['pickup', 'Takeaway', ShoppingBag],
                            ['dine_in', 'Dine-in', UtensilsCrossed],
                        ] as const
                    ).map(([value, label, Icon]) => (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={type === value}
                            onClick={() => props.onType(value)}
                            className={cn('flex h-11 items-center justify-center gap-2 rounded-lg text-sm font-bold transition-colors', type === value ? 'bg-brand-600 text-white' : 'text-ink-300 hover:text-strong')}
                        >
                            <Icon className="size-4" aria-hidden /> {label}
                        </button>
                    ))}
                </div>
                {type === 'dine_in' && tables.length > 0 && (
                    <div className="scrollbar-none relative -mx-1 flex gap-1.5 overflow-x-auto px-1 py-0.5" role="radiogroup" aria-label="Table">
                        {tables.map((table) => (
                            <button
                                key={table.id}
                                type="button"
                                role="radio"
                                aria-checked={tableId === table.id}
                                onClick={() => props.onTable(tableId === table.id ? null : table.id)}
                                className={cn(
                                    'h-11 shrink-0 rounded-lg border px-3 text-sm font-bold whitespace-nowrap transition-colors',
                                    tableId === table.id ? 'border-flame-400 bg-flame-400 text-ink-950' : 'border-ink-600 bg-ink-850 text-ink-100 hover:border-ink-400',
                                )}
                            >
                                {table.name}
                            </button>
                        ))}
                    </div>
                )}
                <input
                    type="text"
                    value={customerName}
                    maxLength={80}
                    onChange={(event) => props.onCustomerName(event.target.value)}
                    placeholder={type === 'dine_in' && tables.length === 0 ? 'Table or name (optional)' : 'Customer name (optional)'}
                    aria-label="Customer name or table"
                    className="h-11 w-full rounded-lg border border-ink-600 bg-ink-850 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                />
            </div>

            <div className="min-h-40 flex-1 overflow-y-auto">
                {receipt && lines.length === 0 && (
                    <div className="m-3 rounded-xl border border-go-500/50 bg-go-900 p-4" role="status">
                        <div className="flex items-start justify-between gap-2">
                            <p className="flex items-center gap-2 text-sm font-bold text-go-400">
                                <CircleCheckBig className="size-5" aria-hidden /> Sent to the kitchen
                            </p>
                            <button type="button" onClick={props.onDismissReceipt} className="text-ink-400 hover:text-strong" aria-label="Dismiss">
                                <X className="size-4" />
                            </button>
                        </div>
                        <p className="mt-2 font-display text-6xl leading-none text-strong">#{receipt.order_number}</p>
                        <p className="mt-1 text-sm text-ink-200">
                            {money(receipt.total)} · {receipt.paid ? `paid, ${receipt.method_label}` : 'to be paid at hand-over'}
                        </p>
                        {receipt.change !== null && (
                            <p className="mt-3 flex items-baseline justify-between rounded-lg bg-ink-950/40 px-3 py-2">
                                <span className="text-xs font-extrabold tracking-wider text-ink-300 uppercase">Change</span>
                                <span className="font-display text-4xl leading-none text-flame-400">{money(receipt.change)}</span>
                            </p>
                        )}
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-bold">
                            <PrintReceiptButton uuid={receipt.uuid} copies="order" variant="secondary" />
                            <Link href={route('counter.index', { order: receipt.uuid })} className="rounded-lg bg-ink-800 px-3 py-2 text-ink-100 hover:bg-ink-700">
                                Open at counter
                            </Link>
                            <a href={route('orders.invoice', receipt.uuid)} className="rounded-lg bg-ink-800 px-3 py-2 text-ink-100 hover:bg-ink-700">
                                PDF
                            </a>
                        </div>
                    </div>
                )}

                {lines.length === 0 ? (
                    <div className="px-4 py-8 text-center">
                        <ReceiptText className="mx-auto size-8 text-ink-600" aria-hidden />
                        <p className="mt-2 text-sm font-semibold text-ink-300">Tap an item to start an order</p>
                        <p className="text-xs text-ink-500">
                            Press <kbd className="rounded bg-ink-800 px-1 font-bold text-ink-300">/</kbd> to search
                        </p>
                        {recent.length > 0 && (
                            <div className="mt-6 text-left">
                                <p className="mb-1.5 text-[10px] font-extrabold tracking-[0.15em] text-ink-500 uppercase">Today at the counter</p>
                                <ul className="divide-y divide-ink-800 rounded-lg border border-ink-800">
                                    {recent.map((order) => (
                                        <li key={order.uuid}>
                                            <Link href={route('counter.index', { order: order.uuid })} className="flex items-center gap-3 px-3 py-2 text-xs hover:bg-ink-850">
                                                <span className="font-display text-xl leading-none text-brand-400">#{order.order_number}</span>
                                                <span className="min-w-0 flex-1 truncate text-ink-300">{order.customer_name}</span>
                                                <span className={cn('font-bold', order.is_paid ? 'text-go-400' : 'text-flame-400')}>{order.is_paid ? 'Paid' : 'Unpaid'}</span>
                                                <span className="font-bold text-ink-100">{money(order.total)}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y divide-ink-800">
                        {lines.map((line) => {
                            const details = describe(line, combo);

                            return (
                                <li key={line.key} className="flex gap-2 px-3 py-2.5">
                                    <div className="flex shrink-0 flex-col items-center rounded-lg bg-ink-850">
                                        <button type="button" onClick={() => props.onQuantity(line.key, line.quantity + 1)} className="flex size-9 items-center justify-center text-ink-200 hover:text-strong" aria-label={`One more ${line.product.name}`}>
                                            <Plus className="size-4" />
                                        </button>
                                        <span className="font-display text-2xl leading-none text-strong" aria-label={`Quantity ${line.quantity}`}>
                                            {line.quantity}
                                        </span>
                                        <button type="button" onClick={() => props.onQuantity(line.key, line.quantity - 1)} className="flex size-9 items-center justify-center text-ink-200 hover:text-strong" aria-label={`One less ${line.product.name}`}>
                                            {line.quantity === 1 ? <Trash2 className="size-4 text-danger-400" /> : <Minus className="size-4" />}
                                        </button>
                                    </div>
                                    <button type="button" onClick={() => props.onEdit(line)} className="group min-w-0 flex-1 rounded-lg px-1 text-left hover:bg-ink-850" aria-label={`Edit ${line.product.name}`}>
                                        <span className="flex items-start justify-between gap-2">
                                            <span className="text-sm leading-snug font-bold text-strong">
                                                {line.product.code && <span className="text-ink-400">{line.product.code} </span>}
                                                {line.product.name}
                                            </span>
                                            <span className="shrink-0 text-sm font-extrabold text-flame-400">{money(unitPrice(line, combo) * line.quantity)}</span>
                                        </span>
                                        {details.length > 0 && (
                                            <span className="mt-1 flex flex-wrap gap-1">
                                                {details.map((detail) => (
                                                    <span
                                                        key={detail}
                                                        className={cn(
                                                            'rounded px-1.5 py-0.5 text-[11px] font-bold',
                                                            /^no /i.test(detail) ? 'bg-danger-900 text-danger-400' : /^extra /i.test(detail) ? 'bg-go-900 text-go-400' : 'bg-ink-700 text-ink-200',
                                                        )}
                                                    >
                                                        {detail}
                                                    </span>
                                                ))}
                                            </span>
                                        )}
                                        {line.notes && <span className="mt-1 block text-xs font-semibold text-flame-300">“{line.notes}”</span>}
                                        <span className="mt-1 flex items-center gap-1 text-[11px] font-semibold text-ink-500 group-hover:text-ink-200">
                                            <Pencil className="size-3" aria-hidden /> Tap to change
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="border-t border-ink-700 p-3">
                <dl className="mb-3 space-y-1 text-xs text-ink-300">
                    <div className="flex justify-between">
                        <dt>
                            {totals.count} item{totals.count === 1 ? '' : 's'}
                        </dt>
                        <dd className="font-semibold text-ink-100">{money(totals.subtotal)}</dd>
                    </div>
                    {tax.rate > 0 && (
                        <div className="flex justify-between">
                            <dt>
                                SST {tax.rate}%{tax.inclusive ? ' (included)' : ''}
                            </dt>
                            <dd className="font-semibold text-ink-100">{money(totals.tax)}</dd>
                        </div>
                    )}
                </dl>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={props.onClear}
                        disabled={lines.length === 0}
                        className="flex h-16 w-16 shrink-0 flex-col items-center justify-center gap-0.5 rounded-xl bg-ink-800 text-[10px] font-bold text-ink-300 hover:bg-ink-700 hover:text-strong disabled:opacity-40"
                    >
                        <Trash2 className="size-4" aria-hidden /> Clear
                    </button>
                    <button
                        type="button"
                        onClick={props.onCharge}
                        disabled={lines.length === 0}
                        className="flex h-16 flex-1 items-center justify-between rounded-xl bg-brand-600 px-4 text-white shadow-glow transition-colors hover:bg-brand-500 disabled:cursor-not-allowed disabled:bg-ink-800 disabled:text-ink-500 disabled:shadow-none"
                    >
                        <span className="text-left">
                            <span className="block text-base leading-tight font-extrabold">Charge</span>
                            <kbd className="text-[10px] font-bold opacity-70">F2</kbd>
                        </span>
                        <span className="font-display text-3xl leading-none whitespace-nowrap lg:text-4xl">{money(totals.total)}</span>
                    </button>
                </div>
            </div>
        </aside>
    );
}
