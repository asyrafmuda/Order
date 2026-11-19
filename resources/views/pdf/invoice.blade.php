@php
    use App\Support\Money;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1f; margin: 0; }
        .header { border-bottom: 3px solid #d91f27; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { font-size: 22px; font-weight: bold; color: #d91f27; text-transform: uppercase; letter-spacing: 1px; }
        .muted { color: #6b6b76; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 0; }
        .items th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b6b76; border-bottom: 1px solid #d6d6dc; padding: 6px 4px; }
        .items td { padding: 8px 4px; border-bottom: 1px solid #ededf0; vertical-align: top; }
        .right { text-align: right; }
        .totals td { padding: 4px; }
        .grand td { font-size: 14px; font-weight: bold; border-top: 2px solid #1a1a1f; padding-top: 8px; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .paid { background: #d1fae5; color: #065f46; }
        .unpaid { background: #fef3c7; color: #92400e; }
        h2 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b6b76; margin: 0 0 4px; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #d6d6dc; font-size: 9px; color: #6b6b76; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="meta">
            <tr>
                <td>
                    <div class="brand">{{ $shop['name'] }}</div>
                    <div class="muted">{{ $shop['tagline'] }}</div>
                    <div class="muted" style="margin-top: 6px;">{{ $shop['address'] }}<br>{{ $shop['phone'] }}</div>
                </td>
                <td class="right">
                    <div style="font-size: 18px; font-weight: bold;">INVOICE</div>
                    <div>Order <strong>#{{ $order->order_number }}</strong></div>
                    <div class="muted">{{ $order->created_at->format('d M Y, g:i A') }}</div>
                    @if ($order->pos_receipt_number)
                        <div class="muted">POS receipt {{ $order->pos_receipt_number }}</div>
                    @endif
                    <div style="margin-top: 6px;">
                        <span class="tag {{ $order->isPaid() ? 'paid' : 'unpaid' }}">{{ $order->payment_status->label() }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta" style="margin-bottom: 18px;">
        <tr>
            <td style="width: 50%;">
                <h2>Billed to</h2>
                <strong>{{ $order->customer_name }}</strong><br>
                +{{ $order->customer_phone }}
                @if ($order->customer_email)
                    <br>{{ $order->customer_email }}
                @endif
            </td>
            <td style="width: 50%;">
                <h2>{{ $order->type->label() }}</h2>
                @if ($order->delivery)
                    {{ $order->delivery->recipient_name }}<br>{{ $order->delivery->full_address }}
                @else
                    Collect at the counter<br>Pickup code: <strong>{{ $order->pickup_code }}</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="right" style="width: 50px;">Qty</th>
                <th class="right" style="width: 90px;">Unit price</th>
                <th class="right" style="width: 90px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->product_code ? $item->product_code.'. ' : '' }}{{ $item->name }}</strong>
                        @php
                            $details = array_filter([
                                $item->variant_name,
                                $item->modifiers->pluck('name')->implode(', '),
                                $item->combo_name ? '+ '.$item->combo_name.($item->combo_choice ? ' ('.$item->combo_choice.')' : '') : null,
                                $item->notes ? 'Note: '.$item->notes : null,
                            ]);
                        @endphp
                        @if ($details)
                            <br><span class="muted">{{ implode(' · ', $details) }}</span>
                        @endif
                    </td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ Money::format($item->unit_price) }}</td>
                    <td class="right">{{ Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 12px; width: 45%; margin-left: 55%;">
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ Money::format($order->subtotal) }}</td>
        </tr>
        @if ($order->delivery_fee > 0)
            <tr>
                <td>Delivery fee</td>
                <td class="right">{{ Money::format($order->delivery_fee) }}</td>
            </tr>
        @endif
        @if ($order->tax_amount > 0)
            <tr>
                <td>SST {{ rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.') }}%{{ $order->tax_inclusive ? ' (included)' : '' }}</td>
                <td class="right">{{ Money::format($order->tax_amount) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="right">{{ Money::format($order->total) }}</td>
        </tr>
        <tr>
            <td class="muted">Payment</td>
            <td class="right muted">{{ $order->payment_method->label() }}</td>
        </tr>
        @if ($order->latestPayment?->gateway_transaction_id)
            <tr>
                <td class="muted">Transaction</td>
                <td class="right muted">{{ $order->latestPayment->gateway_transaction_id }}</td>
            </tr>
        @endif
    </table>

    <div class="footer">
        Thank you for ordering with {{ $shop['name'] }}. This is a computer-generated invoice and requires no signature.
    </div>
</body>
</html>
