<?php

use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\ConfirmPayment;
use App\Actions\Payments\StartPayment;
use App\Data\CheckoutData;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartPricer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // The shop is open around the clock unless a test says otherwise.
        config()->set('shop.defaults', [
            ...config('shop.defaults'),
            'hours.open' => '00:00',
            'hours.close' => '23:59',
            'hours.last_order' => '23:59',
        ]);
        app(Settings::class)->flush();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function userWithRole(UserRole $role): User
{
    $user = User::factory()->role($role)->create();

    if ($role === UserRole::Rider) {
        $user->rider()->create(['vehicle_type' => 'Motorcycle', 'plate_number' => 'TEST 1', 'is_available' => true]);
    }

    return $user;
}

/**
 * A sellable product with its category and kitchen station.
 *
 * @param  array<string, mixed>  $attributes
 */
function menuItem(array $attributes = []): Product
{
    $station = KitchenStation::factory()->create();
    $category = Category::factory()->create(['kitchen_station_id' => $station->id]);

    return Product::factory()->create(['category_id' => $category->id, 'price' => 1500, ...$attributes]);
}

/**
 * Place an order through the real PlaceOrder action.
 *
 * Options: type (OrderType), method (PaymentMethod), paid (bool, pays online
 * orders through the fake gateway), user (User), quantity (int), products (Product[]).
 *
 * @param  array<string, mixed>  $options
 */
function placeOrder(array $options = []): Order
{
    $type = $options['type'] ?? OrderType::Pickup;
    $method = $options['method'] ?? ($type === OrderType::Delivery ? PaymentMethod::Fpx : PaymentMethod::Cash);
    $products = $options['products'] ?? [menuItem()];
    $zone = $type === OrderType::Delivery ? DeliveryZone::factory()->create(['min_order' => 0]) : null;

    $lines = array_map(fn (Product $product, int $index) => [
        'id' => 'line-'.$index,
        'product_id' => $product->id,
        'variant_id' => null,
        'modifier_ids' => [],
        'combo' => false,
        'combo_choice' => null,
        'quantity' => $options['quantity'] ?? 1,
        'notes' => null,
    ], $products, array_keys($products));

    $order = app(PlaceOrder::class)->handle(
        app(CartPricer::class)->price($lines, $type, $zone),
        new CheckoutData(
            customerName: 'Ahmad Faiz',
            customerPhone: '012-345 6789',
            customerEmail: null,
            paymentMethod: $method,
            line1: $zone ? '12 Jalan Bukit' : null,
            city: $zone ? 'Shah Alam' : null,
            postcode: $zone ? '40000' : null,
        ),
        $options['user'] ?? null,
    );

    if ($method->isOnline() && ($options['paid'] ?? true)) {
        app(StartPayment::class)->handle($order);
        $order = app(ConfirmPayment::class)->handle($order->payments()->latest('id')->firstOrFail(), 'TEST-TXN');
    }

    return $order->refresh();
}
