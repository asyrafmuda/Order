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
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartPricer;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            // Guests may pay at the counter unless a test is about that rule.
            'payments.cash_requires_account' => false,
            'payments.cash_max_amount' => 0,
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
 * Switch multi-outlet on and add a second branch next to the main one.
 */
function secondOutlet(string $name = 'Shah Alam'): Outlet
{
    app(Settings::class)->put(['outlets.multi_enabled' => true]);

    $outlet = Outlet::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
    app(OutletContext::class)->flush();

    return $outlet;
}

/**
 * A member of staff who works at the given outlet.
 */
function staffAt(Outlet $outlet, UserRole $role): User
{
    $user = userWithRole($role);
    $user->update(['outlet_id' => $outlet->id]);

    return $user;
}

/**
 * The Admin → Settings form as the page submits it.
 *
 * @param  array<string, array<string, mixed>>  $overrides
 * @return array<string, array<string, mixed>>
 */
function settingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'shop' => [
            'name' => 'Boss Burger HQ', 'tagline' => 'Night Grill', 'address' => '1 Jalan Besar', 'phone' => '+60 12-345 6789',
            'whatsapp' => '60123456789', 'maps_url' => 'https://maps.example.com/boss', 'counter' => 'Counter 5',
        ],
        'outlets' => ['multi_enabled' => false],
        'brand' => [
            'theme' => 'dark', 'icon' => '🍜', 'primary_color' => '#0F766E', 'accent_color' => '#fde047',
            'footer_text' => 'Hand-pulled noodles since 1998.', 'badge_text' => '',
        ],
        'hours' => ['open' => '10:00', 'close' => '22:00', 'last_order' => '21:30'],
        'ordering' => [
            'enabled' => true, 'pickup_enabled' => true, 'delivery_enabled' => false, 'dine_in_enabled' => true,
            'pickup_eta_min' => 10, 'pickup_eta_max' => 25, 'queue_prefix' => 'b', 'auto_close_hours' => 4,
        ],
        'payments' => [
            'fpx_enabled' => true, 'card_enabled' => true, 'ewallet_enabled' => false, 'cash_enabled' => true,
            'cash_requires_account' => false, 'cash_max_amount' => 8000, 'dine_in_pay_at_counter' => true,
        ],
        'pos' => ['quick_notes' => ['Pedas', ' Potong dua ', '']],
        'tax' => ['rate' => 8, 'inclusive' => false],
        'notifications' => ['enabled' => false],
    ], $overrides);
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
 * orders through the fake gateway), user (User), quantity (int), products (Product[]),
 * outlet (Outlet).
 *
 * @param  array<string, mixed>  $options
 */
function placeOrder(array $options = []): Order
{
    $type = $options['type'] ?? OrderType::Pickup;
    $method = $options['method'] ?? ($type === OrderType::Delivery ? PaymentMethod::Fpx : PaymentMethod::Cash);
    $products = $options['products'] ?? [menuItem()];
    $zone = $type === OrderType::Delivery ? DeliveryZone::factory()->create(['min_order' => 0, 'outlet_id' => ($options['outlet'] ?? Outlet::default())->id]) : null;

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

    $place = fn () => app(PlaceOrder::class)->handle(
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

    // 'outlet' => $outlet places the order at that branch.
    $order = isset($options['outlet']) ? app(OutletContext::class)->runAs($options['outlet'], $place) : $place();

    if ($method->isOnline() && ($options['paid'] ?? true)) {
        app(StartPayment::class)->handle($order);
        $order = app(ConfirmPayment::class)->handle($order->payments()->latest('id')->firstOrFail(), 'TEST-TXN');
    }

    return $order->refresh();
}
