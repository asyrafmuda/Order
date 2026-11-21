import { router } from '@inertiajs/react';
import { dateTime, money, time } from '@/lib/format';

/** What /receipts/{order} answers with. */
interface ReceiptData {
    paper: '58' | '80';
    kitchen_slip: boolean;
    shop: { name: string; outlet: string | null; address: string | null; phone: string | null; header: string | null; footer: string | null };
    order: {
        uuid: string;
        number: string;
        placed_at: string | null;
        type_label: string;
        table: string | null;
        customer_name: string | null;
        notes: string | null;
        pickup_code: string | null;
        pos_receipt_number: string | null;
        cancelled: boolean;
    };
    items: { quantity: number; code: string | null; name: string; variant: string | null; modifiers: string[]; combo: string | null; notes: string | null; line_total: number }[];
    totals: { subtotal: number; delivery_fee: number; tax_rate: number; tax_inclusive: boolean; tax_amount: number; total: number };
    payment: { paid: boolean; method_label: string; tendered: number | null; change: number | null; transaction: string | null };
}

/**
 * - "receipt": the customer's receipt only (reprints).
 * - "kitchen": the kitchen slip only.
 * - "order": what a new order prints — the receipt, plus the kitchen slip when
 *   that is switched on in Settings.
 */
export type ReceiptCopies = 'receipt' | 'kitchen' | 'order';

const ROOT_ID = 'receipt-print-root';
const STYLE_ID = 'receipt-print-style';

const escape = (value: string | number | null | undefined) =>
    String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] as string);

const row = (label: string, value: string, className = '') => `<div class="rc-row ${className}"><span>${escape(label)}</span><span>${escape(value)}</span></div>`;

function itemDetails(item: ReceiptData['items'][number]): string[] {
    return [item.variant, ...item.modifiers, item.combo ? `Combo: ${item.combo}` : null].filter((line): line is string => Boolean(line));
}

function orderLine({ order }: ReceiptData): string {
    return [order.type_label, order.table].filter(Boolean).join(' · ');
}

function receiptHtml(data: ReceiptData): string {
    const { shop, order, items, totals, payment } = data;
    const rate = Number.isInteger(totals.tax_rate) ? String(totals.tax_rate) : totals.tax_rate.toFixed(2).replace(/0+$/, '');

    return `
        <section class="rc-copy">
            <div class="rc-center">
                <div class="rc-shop">${escape(shop.name)}</div>
                ${[shop.outlet, shop.address, shop.phone, shop.header].filter(Boolean).map((line) => `<div>${escape(line)}</div>`).join('')}
            </div>
            <hr>
            <div class="rc-row rc-number"><span>Order</span><span>#${escape(order.number)}</span></div>
            <div>${escape(orderLine(data))}</div>
            <div>${escape(dateTime(order.placed_at))}</div>
            ${order.customer_name ? `<div>Customer: ${escape(order.customer_name)}</div>` : ''}
            ${order.pos_receipt_number ? `<div>POS receipt: ${escape(order.pos_receipt_number)}</div>` : ''}
            ${order.cancelled ? '<div class="rc-stamp">CANCELLED</div>' : ''}
            <hr>
            ${items
                .map(
                    (item) => `
                        <div class="rc-item">
                            <div class="rc-row"><span>${item.quantity} x ${escape(item.code ? `${item.code} ` : '')}${escape(item.name)}</span><span>${escape(money(item.line_total, false))}</span></div>
                            ${itemDetails(item).map((line) => `<div class="rc-sub">${escape(line)}</div>`).join('')}
                            ${item.notes ? `<div class="rc-sub">Note: ${escape(item.notes)}</div>` : ''}
                        </div>`,
                )
                .join('')}
            <hr>
            ${row('Subtotal', money(totals.subtotal, false))}
            ${totals.delivery_fee > 0 ? row('Delivery fee', money(totals.delivery_fee, false)) : ''}
            ${totals.tax_amount > 0 ? row(`SST ${rate}%${totals.tax_inclusive ? ' (included)' : ''}`, money(totals.tax_amount, false)) : ''}
            ${row('TOTAL', money(totals.total), 'rc-total')}
            <hr>
            ${payment.paid ? row('Paid', payment.method_label) : '<div class="rc-stamp">NOT PAID YET</div>'}
            ${payment.tendered !== null ? row('Cash received', money(payment.tendered, false)) : ''}
            ${payment.change !== null ? row('Change', money(payment.change, false)) : ''}
            ${payment.transaction ? `<div class="rc-small">Ref: ${escape(payment.transaction)}</div>` : ''}
            ${order.pickup_code ? `<hr><div class="rc-center">Pickup code<div class="rc-code">${escape(order.pickup_code)}</div></div>` : ''}
            ${order.notes ? `<hr><div>Note: ${escape(order.notes)}</div>` : ''}
            ${shop.footer ? `<hr><div class="rc-center">${escape(shop.footer)}</div>` : ''}
        </section>`;
}

function kitchenHtml(data: ReceiptData): string {
    const { order, items } = data;

    return `
        <section class="rc-copy rc-kitchen">
            <div class="rc-row"><span class="rc-small">KITCHEN</span><span class="rc-small">${escape(time(order.placed_at))}</span></div>
            <div class="rc-kitchen-number">#${escape(order.number)}</div>
            <div class="rc-kitchen-type">${escape(orderLine(data))}</div>
            ${order.customer_name ? `<div>${escape(order.customer_name)}</div>` : ''}
            <hr>
            ${items
                .map(
                    (item) => `
                        <div class="rc-item">
                            <div class="rc-kitchen-item">${item.quantity} x ${escape(item.name)}</div>
                            ${itemDetails(item).map((line) => `<div class="rc-sub">${escape(line)}</div>`).join('')}
                            ${item.notes ? `<div class="rc-sub rc-note">** ${escape(item.notes)} **</div>` : ''}
                        </div>`,
                )
                .join('')}
            ${order.notes ? `<hr><div class="rc-note">Note: ${escape(order.notes)}</div>` : ''}
        </section>`;
}

/**
 * Everything on the page is hidden while printing except the receipt, which
 * is as wide as the paper's printable area. Sizes are in mm and pt so the
 * print is the same from a phone, a tablet or a PC.
 */
function styles(paper: '58' | '80'): string {
    const width = paper === '58' ? 48 : 72;
    const base = paper === '58' ? 8.5 : 9.5;

    return `
        #${ROOT_ID} { display: none; }
        @page { margin: 0; }
        @media print {
            html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; height: auto !important; }
            body > *:not(#${ROOT_ID}) { display: none !important; }
            #${ROOT_ID} { display: block; width: ${width}mm; margin: 0; padding: 2mm 0 6mm; color: #000; font: ${base}pt/1.3 Arial, Helvetica, sans-serif; }
            #${ROOT_ID} * { color: #000 !important; }
            #${ROOT_ID} .rc-copy + .rc-copy { break-before: page; page-break-before: always; padding-top: 2mm; }
            #${ROOT_ID} hr { border: 0; border-top: 1px dashed #000; margin: 1.5mm 0; }
            #${ROOT_ID} .rc-center { text-align: center; }
            #${ROOT_ID} .rc-shop { font-size: ${base + 5}pt; font-weight: 700; text-transform: uppercase; }
            #${ROOT_ID} .rc-row { display: flex; justify-content: space-between; gap: 2mm; }
            #${ROOT_ID} .rc-row > span:last-child { text-align: right; white-space: nowrap; }
            #${ROOT_ID} .rc-number { font-size: ${base + 7}pt; font-weight: 700; }
            #${ROOT_ID} .rc-item { margin-bottom: 1mm; break-inside: avoid; }
            #${ROOT_ID} .rc-sub { padding-left: 4mm; }
            #${ROOT_ID} .rc-total { font-size: ${base + 4}pt; font-weight: 700; margin-top: 0.5mm; }
            #${ROOT_ID} .rc-stamp { font-weight: 700; text-align: center; border: 1px solid #000; padding: 0.5mm; margin: 1mm 0; }
            #${ROOT_ID} .rc-small { font-size: ${base - 1.5}pt; }
            #${ROOT_ID} .rc-code { font-size: ${base + 7}pt; font-weight: 700; letter-spacing: 1mm; }
            #${ROOT_ID} .rc-kitchen-number { font-size: ${base + 18}pt; font-weight: 700; line-height: 1.1; }
            #${ROOT_ID} .rc-kitchen-type { font-size: ${base + 4}pt; font-weight: 700; }
            #${ROOT_ID} .rc-kitchen-item { font-size: ${base + 4}pt; font-weight: 700; }
            #${ROOT_ID} .rc-kitchen .rc-sub { font-size: ${base + 1.5}pt; }
            #${ROOT_ID} .rc-note { font-weight: 700; }
        }`;
}

let pendingCleanup: number | undefined;
let mountedOn: string | null = null;

function cleanUp(): void {
    window.clearTimeout(pendingCleanup);
    document.getElementById(ROOT_ID)?.remove();
    document.getElementById(STYLE_ID)?.remove();
    mountedOn = null;
}

// Another page must never print the last receipt by accident. (Screens that
// refresh themselves stay on the same address and keep it.)
router.on('navigate', () => {
    if (mountedOn !== null && mountedOn !== window.location.pathname) {
        cleanUp();
    }
});

/** Lay the receipt out in the page (hidden on screen) and return its container. */
export function mountReceipt(data: ReceiptData, copies: ReceiptCopies): HTMLElement {
    cleanUp();

    const withReceipt = copies !== 'kitchen';
    const withKitchen = copies === 'kitchen' || (copies === 'order' && data.kitchen_slip);

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = styles(data.paper);
    document.head.appendChild(style);

    const root = document.createElement('div');
    root.id = ROOT_ID;
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML = (withReceipt ? receiptHtml(data) : '') + (withKitchen ? kitchenHtml(data) : '');
    document.body.appendChild(root);
    mountedOn = window.location.pathname;

    return root;
}

async function print(data: ReceiptData, copies: ReceiptCopies): Promise<void> {
    mountReceipt(data, copies);

    // Desktop browsers tell when the print dialog closes. Phones and tablets
    // often say so too early, so there the receipt stays in place for a while.
    if (window.matchMedia('(pointer: fine)').matches) {
        window.addEventListener('afterprint', cleanUp, { once: true });
    }

    pendingCleanup = window.setTimeout(cleanUp, 120_000);

    // Let the browser lay the receipt out before the print dialog captures the page.
    await new Promise((resolve) => window.requestAnimationFrame(() => window.setTimeout(resolve, 50)));
    window.print();
}

/**
 * A made-up order, to check the printer and paper width from Settings
 * before the first real sale.
 */
export function printSampleReceipt(options: { paper: '58' | '80'; kitchenSlip: boolean; shop: ReceiptData['shop'] }): Promise<void> {
    return print(
        {
            paper: options.paper,
            kitchen_slip: options.kitchenSlip,
            shop: options.shop,
            order: {
                uuid: 'sample',
                number: 'A001',
                placed_at: new Date().toISOString(),
                type_label: 'Takeaway',
                table: null,
                customer_name: 'Test print',
                notes: null,
                pickup_code: null,
                pos_receipt_number: null,
                cancelled: false,
            },
            items: [
                { quantity: 2, code: 'B1', name: 'Beef Burger', variant: 'Double', modifiers: ['Extra cheese', 'No onion'], combo: null, notes: 'Sauce on the side', line_total: 3180 },
                { quantity: 1, code: 'D1', name: 'Iced Lemon Tea', variant: null, modifiers: [], combo: null, notes: null, line_total: 390 },
            ],
            totals: { subtotal: 3570, delivery_fee: 0, tax_rate: 6, tax_inclusive: true, tax_amount: 202, total: 3570 },
            payment: { paid: true, method_label: 'Cash', tendered: 5000, change: 1430, transaction: null },
        },
        'order',
    );
}

/**
 * Print an order's receipt on the device's printer. Works with any thermal
 * printer the device can print to (USB, network or Bluetooth), through the
 * browser's print dialog.
 *
 * @throws Error with a message for the cashier when the receipt cannot be loaded
 */
export async function printReceipt(uuid: string, copies: ReceiptCopies = 'receipt'): Promise<void> {
    const response = await fetch(route('receipts.show', uuid), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(response.status === 404 ? 'This order could not be found.' : 'The receipt could not be loaded. Check the connection and try again.');
    }

    await print((await response.json()) as ReceiptData, copies);
}
