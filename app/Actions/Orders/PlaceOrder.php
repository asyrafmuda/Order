<?php

namespace App\Actions\Orders;

use App\Data\CheckoutData;
use App\Enums\DeliveryStatus;
use App\Enums\KitchenItemStatus;
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
        private readonly TransitionOrder $transition,
    ) {}

    /**
     * @throws OrderException
     */
    public function handle(PricedCart $cart, CheckoutData $data, ?User $user = null): Order
    {
        $this->guard($cart, $data);

        // Retried on deadlock / duplicate queue number under concurrent checkouts.
        $order = DB::transaction(fn () => $this->create($cart, $data, $user), attempts: 3);

        // Cash orders go straight to the kitchen; online orders wait for payment.
        if (! $data->paymentMethod->isOnline()) {
            $order = $this->transition->handle($order, OrderStatus::Confirmed, note: 'Pay at counter');
        }

        return $order;
    }

    /**
     * @throws OrderException
     */
    private function guard(PricedCart $cart, CheckoutData $data): void
    {
        if ($cart->isEmpty()) {
            throw OrderException::emptyCart();
        }

        if (! $this->hours->isAcceptingOrders()) {
            throw OrderException::shopClosed($this->hours->closedMessage());
        }

        if ($cart->type === OrderType::Delivery) {
            if (! $cart->zone) {
                throw new OrderException('Please choose a delivery area.');
            }

            if ($cart->minOrderShortfall() > 0) {
                throw new OrderException("The minimum order for {$cart->zone->name} has not been reached.");
            }

            if (! $data->paymentMethod->isOnline()) {
                throw new OrderException('Delivery orders must be paid online.');
            }
        }
    }

    private function create(PricedCart $cart, CheckoutData $data, ?User $user): Order
    {
        $businessDate = now()->toDateString();

        $queueNumber = (int) Order::query()
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->max('queue_number') + 1;

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_date' => $businessDate,
            'queue_number' => $queueNumber,
            'order_number' => sprintf('%s%03d', $this->settings->string('ordering.queue_prefix'), $queueNumber),
            'pickup_code' => $this->pickupCode(),
            'user_id' => $user?->id,
            'customer_name' => $data->customerName,
            'customer_phone' => Phone::normalize($data->customerPhone),
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
            'user_id' => $user?->id,
            'note' => 'Order placed online',
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
