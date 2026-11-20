import { Link, usePage } from '@inertiajs/react';
import { Minus, Plus, ReceiptText, Trash2, X, Zap } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { buttonClass } from '@/components/ui/Button';
import { cartActions } from '@/lib/cart';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import type { Cart, CartLine } from '@/types';

interface TicketPanelProps {
    /** Replaces the default "Proceed to Checkout & Pay" button (the checkout page passes its own submit). */
    action?: ReactNode;
    className?: string;
}

/** The "Current Ticket": cart lines, totals and the checkout call to action. */
export function TicketPanel({ action, className }: TicketPanelProps) {
    const { cart, shop } = usePage().props;

    if (!cart) return null;

    return (
        <section className={cn('rounded-xl border border-ink-700 bg-ink-850', className)} aria-label="Current ticket">
            <header className="flex items-center justify-between gap-3 border-b border-ink-700 px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <ReceiptText className="size-5 text-flame-400" aria-hidden />
                    <div>
                        <h2 className="text-sm leading-tight font-bold text-white">Current Ticket</h2>
                        <p className="text-[11px] font-semibold text-go-400">
                            {cart.count > 0 ? `${cart.count} item${cart.count === 1 ? '' : 's'}` : 'Nothing added yet'}
                        </p>
                    </div>
                </div>
                <Badge tone="solid-red">{cart.table ? cart.table.name : cart.type === 'delivery' ? 'Delivery' : 'Pickup'}</Badge>
            </header>

            {cart.lines.length === 0 ? (
                <div className="px-4 py-10 text-center">
                    <p className="text-3xl" aria-hidden>
                        {shop.icon}
                    </p>
                    <p className="mt-2 text-sm font-semibold text-ink-200">Your ticket is empty</p>
                    <p className="text-xs text-ink-400">Pick something from the menu to get started.</p>
                </div>
            ) : (
                <>
                    <ul className="divide-y divide-ink-700">
                        {cart.lines.map((line) => (
                            <TicketLine key={line.id} line={line} />
                        ))}
                    </ul>

                    {cart.upsells.map((upsell) => (
                        <div key={upsell.id} className="flex items-center justify-between gap-3 border-t border-dashed border-ink-600 px-4 py-3">
                            <div>
                                <p className="text-xs font-bold text-ink-100">Add {upsell.name}</p>
                                <p className="text-xs font-bold text-flame-400">+{money(upsell.price)}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => cartActions.add({ product_id: upsell.id })}
                                className="rounded-md bg-ink-700 px-2.5 py-1 text-xs font-bold text-ink-100 hover:bg-ink-600"
                            >
                                + Add
                            </button>
                        </div>
                    ))}

                    <Totals cart={cart} />
                </>
            )}

            <div className="space-y-2 border-t border-ink-700 p-4">
                {cart.min_order_shortfall > 0 && (
                    <p className="rounded-lg bg-flame-900 px-3 py-2 text-xs font-semibold text-flame-300">
                        Add {money(cart.min_order_shortfall)} more to reach the delivery minimum for {cart.zone?.name}.
                    </p>
                )}
                {action ??
                    (cart.lines.length > 0 && shop.is_open ? (
                        <Link href={route('checkout.create')} className={buttonClass('primary', 'lg', 'w-full')}>
                            <Zap className="size-4" aria-hidden />
                            Proceed to Checkout &amp; Pay
                        </Link>
                    ) : (
                        <span className={buttonClass('primary', 'lg', 'w-full cursor-not-allowed opacity-50')} aria-disabled>
                            <Zap className="size-4" aria-hidden />
                            {shop.is_open ? 'Proceed to Checkout & Pay' : 'Ordering is closed'}
                        </span>
                    ))}
                <p className="text-center text-[11px] text-ink-500">Your order goes straight to the {shop.name} kitchen once it is confirmed.</p>
                {cart.lines.length > 0 && !action && (
                    <button type="button" onClick={() => cartActions.clear()} className="mx-auto flex items-center gap-1 text-[11px] font-semibold text-ink-500 hover:text-brand-400">
                        <Trash2 className="size-3" aria-hidden /> Clear ticket
                    </button>
                )}
            </div>
        </section>
    );
}

function TicketLine({ line }: { line: CartLine }) {
    const details = [
        line.variant_name,
        ...line.modifiers.map((modifier) => (modifier.price > 0 ? `${modifier.name} (+${money(modifier.price)})` : modifier.name)),
        line.combo && `+${line.combo.name}${line.combo.choice ? `: ${line.combo.choice}` : ''} (+${money(line.combo.price)})`,
    ].filter(Boolean);

    return (
        <li className="px-4 py-3">
            <div className="flex items-start justify-between gap-3">
                <p className="min-w-0 text-[13px] leading-snug font-bold text-white">
                    {line.code && <span className="text-ink-300">{line.code}. </span>}
                    {line.name}
                </p>
                <div className="flex shrink-0 items-center gap-2">
                    <span className="text-sm font-extrabold text-flame-400">{money(line.line_total)}</span>
                    <button type="button" onClick={() => cartActions.remove(line.id)} className="text-ink-500 hover:text-brand-400" aria-label={`Remove ${line.name}`}>
                        <X className="size-4" />
                    </button>
                </div>
            </div>
            {details.length > 0 && <p className="mt-0.5 text-[11px] leading-relaxed text-ink-400">{details.join(' • ')}</p>}
            {line.notes && <p className="mt-0.5 text-[11px] font-medium text-go-400">Notes: {line.notes}</p>}
            <div className="mt-2 flex items-center justify-end gap-1">
                <span className="mr-1 text-[11px] text-ink-500">Qty</span>
                <button type="button" onClick={() => cartActions.setQuantity(line.id, line.quantity - 1)} className="flex size-6 items-center justify-center rounded-md bg-ink-700 text-ink-200 hover:bg-ink-600" aria-label={`Decrease ${line.name}`}>
                    <Minus className="size-3" />
                </button>
                <span className="w-6 text-center text-xs font-bold">{line.quantity}</span>
                <button type="button" onClick={() => cartActions.setQuantity(line.id, line.quantity + 1)} className="flex size-6 items-center justify-center rounded-md bg-ink-700 text-ink-200 hover:bg-ink-600" aria-label={`Increase ${line.name}`}>
                    <Plus className="size-3" />
                </button>
            </div>
        </li>
    );
}

export function Totals({ cart }: { cart: Pick<Cart, 'subtotal' | 'delivery_fee' | 'tax_amount' | 'tax_rate' | 'tax_inclusive' | 'total' | 'type'> }) {
    return (
        <dl className="space-y-1.5 border-t border-ink-700 px-4 py-3 text-xs">
            <div className="flex justify-between text-ink-300">
                <dt>Subtotal</dt>
                <dd className="font-semibold text-ink-100">{money(cart.subtotal)}</dd>
            </div>
            <div className="flex justify-between text-ink-300">
                <dt>{cart.type === 'delivery' ? 'Delivery fee' : cart.type === 'dine_in' ? 'Table service' : 'Order handling (Self Pickup)'}</dt>
                <dd className={cn('font-semibold', cart.delivery_fee > 0 ? 'text-ink-100' : 'text-go-400')}>{money(cart.delivery_fee)}</dd>
            </div>
            {cart.tax_rate > 0 && (
                <div className="flex justify-between text-ink-300">
                    <dt>
                        SST ({cart.tax_rate}%{cart.tax_inclusive ? ' inc.' : ''})
                    </dt>
                    <dd className="font-semibold text-ink-100">{cart.tax_inclusive ? `Included (${money(cart.tax_amount)})` : money(cart.tax_amount)}</dd>
                </div>
            )}
            <div className="flex items-end justify-between pt-2">
                <dt className="text-sm font-bold text-white">Total Amount</dt>
                <dd className="font-display text-4xl leading-none whitespace-nowrap text-flame-400">{money(cart.total)}</dd>
            </div>
        </dl>
    );
}
