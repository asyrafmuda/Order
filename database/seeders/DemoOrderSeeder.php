<?php

namespace Database\Seeders;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Two weeks of completed sample orders so the admin dashboard and reports
 * have something to show. Not part of the default seed.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->with('variants')->where('status', 'available')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('Seed the menu first: php artisan db:seed');

            return;
        }

        foreach (range(14, 1) as $daysAgo) {
            $day = CarbonImmutable::today()->subDays($daysAgo);
            $existing = (int) Order::query()->where('business_date', $day->toDateString())->max('queue_number');

            foreach (range(1, random_int(8, 22)) as $index) {
                $placedAt = $day->setTime(random_int(12, 22), random_int(0, 59));
                $queue = $existing + $index;
                $lines = $products->random(random_int(1, 3));
                $subtotal = 0;

                $order = Order::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'business_date' => $day->toDateString(),
                    'queue_number' => $queue,
                    'order_number' => sprintf('A%03d', $queue),
                    'pickup_code' => strtoupper(Str::random(8)),
                    'customer_name' => fake()->name(),
                    'customer_phone' => '601'.fake()->numerify('########'),
                    'type' => OrderType::Pickup,
                    'status' => OrderStatus::Completed,
                    'payment_status' => PaymentStatus::Paid,
                    'payment_method' => fake()->randomElement([PaymentMethod::Fpx, PaymentMethod::Card, PaymentMethod::Cash]),
                    'subtotal' => 0,
                    'total' => 0,
                    'tax_rate' => 6,
                    'tax_inclusive' => true,
                    'confirmed_at' => $placedAt,
                    'preparing_at' => $placedAt->addMinutes(2),
                    'ready_at' => $placedAt->addMinutes(14),
                    'completed_at' => $placedAt->addMinutes(18),
                    'pos_sync_status' => SyncStatus::Synced,
                    'pos_receipt_number' => sprintf('LV-%s%03d', $day->format('md'), $queue),
                    'pos_synced_at' => $placedAt,
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt->addMinutes(18),
                ]);

                foreach ($lines as $product) {
                    $variant = $product->variants->isNotEmpty() ? $product->variants->random() : null;
                    $price = $variant?->price ?? $product->price;
                    $quantity = random_int(1, 2);
                    $subtotal += $price * $quantity;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'kitchen_station_id' => $product->kitchen_station_id,
                        'product_code' => $product->code,
                        'name' => $product->name,
                        'variant_name' => $variant?->name,
                        'base_price' => $price,
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'line_total' => $price * $quantity,
                        'kitchen_status' => KitchenItemStatus::Done,
                        'created_at' => $placedAt,
                        'updated_at' => $placedAt,
                    ]);
                }

                $order->forceFill([
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'tax_amount' => Money::tax($subtotal, 6, true),
                    'updated_at' => $placedAt->addMinutes(18),
                ])->saveQuietly();
            }
        }
    }
}
