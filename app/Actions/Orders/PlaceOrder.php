<?php

namespace App\Actions\Orders;

use App\Actions\Payments\CollectCashPayment;
use App\Data\CheckoutData;
use App\Enums\DeliveryStatus;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Exceptions\OrderException;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\PricedCart;
use App\Services\Cart\PricedLine;
use App\Support\OutletContext;
use App\Support\PaymentOptions;
use App\Support\Phone;
use App\Support\Settings;
use App\Support\ShopHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a priced cart into an order. Product names and prices are copied
 * onto the order so later menu changes never rewrite history.
 */
class PlaceOrder
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ShopHours $hours,
        private readonly PaymentOptions $paymentOptions,
        private readonly TransitionOrder $transition,
        private readonly CollectCashPayment $collectPayment,
        private readonly OutletContext $outlets,
    ) {}

    /**
     * @throws OrderException
     */
    public function handle(PricedCart $cart, CheckoutData $data, ?User $user = null): Order
    {
        $this->guard($cart, $data, $user);

        // Retried on deadlock / duplicate queue number under concurrent checkouts.
        $order = DB::transaction(fn () => $this->create($cart, $data, $user), attempts: 3);

        // Counter sales go straight to the kitchen, paid or to be paid at hand-over.
        if ($data->source === OrderSource::Pos) {
            $order = $this->transition->handle($order, OrderStatus::Confirmed, $data->placedBy, 'Taken at the counter');

            return $data->paidAtCounter ? $this->collectPayment->handle($order, $data->placedBy) : $order;
        }

        // Online: cash orders go straight to the kitchen; the rest wait for payment.
        if (! $data->paymentMethod->isOnline()) {
            $order = $this->transition->handle($order, OrderStatus::Confirmed, note: $order->isDineIn() ? 'Ordered from the table, pay at counter' : 'Pay at counter');
        }

        return $order;
    }

    /**
     * @throws OrderException
     */
    private function guard(PricedCart $cart, CheckoutData $data, ?User $user): void
    {
        if ($cart->isEmpty()) {
            throw OrderException::emptyCart();
        }

        // Staff at the counter decide for themselves when to take an order
        // and how it is paid; the rules below are for online customers.
        if ($data->source === OrderSource::Pos) {
            return;
        }

        if (! $this->hours->isAcceptingOrders()) {
            throw OrderException::shopClosed($this->hours->closedMessage());
        }

        $channelOpen = match ($cart->type) {
            OrderType::Pickup => $this->settings->bool('ordering.pickup_enabled'),
            OrderType::Delivery => $this->settings->bool('ordering.delivery_enabled'),
            OrderType::DineIn => $this->settings->bool('ordering.dine_in_enabled'),
        };

        if (! $channelOpen) {
            throw new OrderException("{$cart->type->label()} orders are not available right now.");
        }

        if ($cart->type === OrderType::DineIn && ! $data->table) {
            throw new OrderException('Scan the QR code on your table to order for dine-in.');
        }

        $reason = $this->paymentOptions->unavailableReason($data->paymentMethod, $cart->type, $user, $cart->total(), $data->atCounter);

        if ($reason !== null) {
            throw new OrderException("{$data->paymentMethod->label()} cannot be used for this order: {$reason}.");
        }

        if ($cart->type === OrderType::Delivery) {
            if (! $cart->zone) {
                throw new OrderException('Please choose a delivery area.');
            }

            if ($cart->minOrderShortfall() > 0) {
                throw new OrderException("The minimum order for {$cart->zone->name} has not been reached.");
            }

        }
    }

    private function create(PricedCart $cart, CheckoutData $data, ?User $user): Order
    {
        // The order belongs to the outlet the request is working in: the one
        // the customer picked (or whose table they scanned), or the staff member's own.
        $outlet = $this->outlets->current();
        $businessDate = now()->toDateString();

        $queueNumber = (int) Order::query()
            ->forOutlet($outlet)
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->max('queue_number') + 1;

        $order = Order::query()->create([
            'outlet_id' => $outlet->id,
            'uuid' => (string) Str::uuid(),
            'business_date' => $businessDate,
            'queue_number' => $queueNumber,
            'order_number' => sprintf('%s%03d', $this->settings->string('ordering.queue_prefix'), $queueNumber),
            'pickup_code' => $this->pickupCode(),
            'user_id' => $user?->id,
            'placed_by_id' => $data->placedBy?->id,
            'source' => $data->source,
            'dining_table_id' => $cart->type === OrderType::DineIn ? $data->table?->id : null,
            'table_label' => $cart->type === OrderType::DineIn ? $data->table?->name : null,
            'customer_name' => filled($data->customerName) ? $data->customerName : ($data->table?->name ?? ($data->atCounter ? 'Walk-in' : 'Guest')),
            'customer_phone' => filled($data->customerPhone) ? Phone::normalize($data->customerPhone) : null,
            'customer_email' => $data->customerEmail,
            'type' => $cart->type,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => $data->paymentMethod,
            'notes' => $data->notes,
            'subtotal' => $cart->subtotal(),
            'delivery_fee' => $cart->deliveryFee(),
            'tax_amount' => $cart->taxAmount(),
            'tax_rate' => $cart->taxRate,
            'tax_inclusive' => $cart->taxInclusive,
            'total' => $cart->total(),
            'pos_sync_status' => SyncStatus::Pending,
        ]);

        $cart->lines->each(function (PricedLine $line) use ($order) {
            $item = $order->items()->create([
                'product_id' => $line->product->id,
                'product_variant_id' => $line->variant?->id,
                'kitchen_station_id' => $line->product->resolveStationId(),
                'product_code' => $line->product->code,
                'name' => $line->product->name,
                'variant_name' => $line->variant?->name,
                'combo_name' => $line->combo?->name,
                'combo_choice' => $line->comboChoice,
                'combo_price' => $line->comboPrice(),
                'base_price' => $line->basePrice(),
                'unit_price' => $line->unitPrice(),
                'quantity' => $line->quantity,
                'line_total' => $line->lineTotal(),
                'notes' => $line->notes,
                'kitchen_status' => KitchenItemStatus::Queued,
            ]);

            $item->modifiers()->createMany($line->modifiers->map(fn (Modifier $modifier) => [
                'modifier_id' => $modifier->id,
                'group_name' => $line->product->modifierGroups->firstWhere('id', $modifier->modifier_group_id)?->name,
                'name' => $modifier->name,
                'price' => $modifier->price,
            ])->all());
        });

        if ($cart->type === OrderType::Delivery) {
            $order->delivery()->create([
                'delivery_zone_id' => $cart->zone->id,
                'status' => DeliveryStatus::Pending,
                'recipient_name' => $data->recipientName ?: $data->customerName,
                'phone' => Phone::normalize($data->recipientPhone ?: $data->customerPhone),
                'line1' => $data->line1,
                'line2' => $data->line2,
                'city' => $data->city,
                'postcode' => $data->postcode,
                'notes' => $data->deliveryNotes,
                'fee' => $cart->deliveryFee(),
            ]);
        }

        $order->histories()->create([
            'status' => OrderStatus::PendingPayment,
            'user_id' => $data->placedBy?->id ?? $user?->id,
            'note' => $data->source === OrderSource::Pos ? 'Order keyed in at the counter' : 'Order placed online',
        ]);

        return $order;
    }

    /**
     * A short code the customer shows at the counter. Ambiguous characters
     * (0/O, 1/I) are left out.
     */
    private function pickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Order::query()->where('pickup_code', $code)->exists());

        return $code;
    }
}
