<?php

namespace App\Http\Middleware;

use App\Contracts\PosProvider;
use App\Models\Outlet;
use App\Services\Cart\Cart;
use App\Support\BrandPalette;
use App\Support\KitchenLoad;
use App\Support\Licensing\License;
use App\Support\OutletContext;
use App\Support\Settings;
use App\Support\ShopHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isBackOffice = $request->routeIs('admin.*', 'kitchen.*', 'counter.*', 'rider.*', 'pos.*');

        return [
            ...parent::share($request),
            'auth' => fn () => ['user' => $this->user($request)],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'receipt' => $request->session()->get('receipt'),
            ],
            'shop' => fn () => $this->shop(),
            // Dark or light plus the brand colours, as CSS the page applies straight away.
            'theme' => fn () => ['mode' => app(BrandPalette::class)->theme(), 'css' => app(BrandPalette::class)->css(), 'page_color' => app(BrandPalette::class)->pageColor()],
            'kitchen' => fn () => app(KitchenLoad::class)->snapshot(),
            'pos' => fn () => $this->pos(app(PosProvider::class)),
            'cart' => fn () => $isBackOffice ? null : app(Cart::class)->toArray(),
            'outlet' => fn () => $this->outlet($request, $isBackOffice),
            'realtime' => filled(config('broadcasting.connections.reverb.key'))
                && config('broadcasting.default') === 'reverb',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function user(Request $request): ?array
    {
        $user = $request->user();

        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'is_staff' => $user->isStaff(),
            'is_admin' => $user->isAdmin(),
            // Which licensing screens the back office offers.
            'licensing' => $user->isAdmin() ? [
                'client' => app(License::class)->configured(),
                'server' => app(License::class)->isLicenseServer(),
            ] : null,
            'home_url' => route($user->role->homeRoute()),
        ] : null;
    }

    /**
     * The outlet in use, and the choices on offer when the business has more
     * than one: every active outlet for customers and super admins, none for
     * staff tied to their own.
     *
     * @return array{multi: bool, current: array{id: int, name: string}, options: array<int, array<string, mixed>>, can_switch: bool}
     */
    private function outlet(Request $request, bool $isBackOffice): array
    {
        $context = app(OutletContext::class);
        $current = $context->current();
        $multi = $context->multi();
        $user = $request->user();
        $canSwitch = $multi && ($isBackOffice ? (bool) $user?->isAdmin() : ! $user?->isOutletStaff());

        return [
            'multi' => $multi,
            'current' => ['id' => $current->id, 'name' => $current->name],
            'can_switch' => $canSwitch,
            'options' => $canSwitch
                ? Outlet::query()
                    ->when(! $isBackOffice, fn ($query) => $query->active())
                    ->ordered()
                    ->get()
                    ->map(fn (Outlet $outlet) => [
                        'id' => $outlet->id,
                        'name' => $outlet->name,
                        'address' => app(Settings::class)->forOutlet($outlet)['shop.address'] ?? '',
                        'is_active' => $outlet->is_active,
                    ])
                    ->all()
                : [],
        ];
    }

    /**
     * The stand-in POS is labelled as a simulation on every screen, so nobody
     * mistakes a demo for a live Loyverse connection.
     *
     * @return array{provider: string, label: string, connected: bool, simulated: bool}
     */
    private function pos(PosProvider $pos): array
    {
        $simulated = $pos->name() === 'fake';

        return [
            'provider' => $pos->name(),
            'label' => $simulated ? 'Simulated' : ucfirst($pos->name()),
            'connected' => $pos->isConfigured(),
            'simulated' => $simulated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shop(): array
    {
        $settings = app(Settings::class);
        $hours = app(ShopHours::class);
        $open = $hours->isAcceptingOrders();

        return [
            'name' => $settings->string('shop.name'),
            'icon' => $settings->string('brand.icon') ?: '🍽️',
            'logo_url' => $settings->get('brand.logo_path') ? Storage::disk('public')->url($settings->get('brand.logo_path')) : null,
            'footer_text' => $settings->string('brand.footer_text'),
            'badge_text' => $settings->string('brand.badge_text'),
            'tagline' => $settings->string('shop.tagline'),
            'address' => $settings->string('shop.address'),
            'phone' => $settings->string('shop.phone'),
            'whatsapp' => $settings->string('shop.whatsapp'),
            'maps_url' => $settings->string('shop.maps_url'),
            'counter' => $settings->string('shop.counter'),
            'hours' => $hours->toArray(),
            'is_open' => $open,
            'closed_message' => $open ? null : $hours->closedMessage(),
            'pickup_enabled' => $settings->bool('ordering.pickup_enabled'),
            'delivery_enabled' => $settings->bool('ordering.delivery_enabled'),
            'dine_in_enabled' => $settings->bool('ordering.dine_in_enabled'),
        ];
    }
}
