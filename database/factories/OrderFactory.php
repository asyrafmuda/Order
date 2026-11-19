<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $queue = ++self::$sequence;
        $total = fake()->numberBetween(15, 90) * 100;

        return [
            'uuid' => (string) Str::uuid(),
            'business_date' => now()->toDateString(),
            'queue_number' => $queue,
            'order_number' => sprintf('A%03d', $queue),
            'pickup_code' => strtoupper(Str::random(6)),
            'customer_name' => fake()->name(),
            'customer_phone' => '601'.fake()->numerify('########'),
            'type' => OrderType::Pickup,
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Fpx,
            'subtotal' => $total,
            'delivery_fee' => 0,
            'tax_amount' => (int) round($total * 6 / 106),
            'tax_rate' => 6,
            'tax_inclusive' => true,
            'total' => $total,
            'confirmed_at' => now(),
            'pos_sync_status' => SyncStatus::Pending,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'confirmed_at' => null,
        ]);
    }
}
