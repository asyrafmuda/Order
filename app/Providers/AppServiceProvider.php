<?php

namespace App\Providers;

use App\Contracts\MessageChannel;
use App\Contracts\PaymentGateway;
use App\Contracts\PosProvider;
use App\Services\Cart\Cart;
use App\Services\Cart\CartPricer;
use App\Services\Messaging\WhatsAppChannel;
use App\Services\Pos\LoyversePosProvider;
use App\Support\KitchenLoad;
use App\Support\OutletContext;
use App\Support\OutletStock;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->scoped(OutletContext::class);
        $this->app->scoped(OutletStock::class);
        $this->app->scoped(KitchenLoad::class);
        $this->app->scoped(Cart::class, fn ($app) => new Cart(
            $app['session.store'],
            $app->make(CartPricer::class),
            $app->make(Settings::class),
            $app->make(OutletContext::class),
            $app->make(OutletStock::class),
        ));

        // Integrations are chosen by name in config, so swapping a provider
        // is an .env change rather than a code change.
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(
            $this->driver('payment', 'Payment gateway'),
        ));

        $this->app->when(LoyversePosProvider::class)->needs('$config')->giveConfig('pos.loyverse');
        $this->app->bind(PosProvider::class, fn ($app) => $app->make($this->driver('pos', 'POS provider')));

        $this->app->when(WhatsAppChannel::class)->needs('$config')->giveConfig('messaging.whatsapp');
        $this->app->bind(MessageChannel::class, fn ($app) => $app->make(
            $this->driver('messaging', 'Messaging channel'),
        ));
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * @return class-string
     */
    private function driver(string $config, string $label): string
    {
        $name = config("{$config}.default");
        $class = config("{$config}.drivers.{$name}");

        if (! $class) {
            throw new InvalidArgumentException("{$label} [{$name}] is not defined in config/{$config}.php.");
        }

        return $class;
    }
}
