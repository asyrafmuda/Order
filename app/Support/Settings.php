<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runtime shop settings. Defaults come from config/shop.php; values saved in
 * Admin → Settings override them.
 *
 * Most settings apply to the whole business. The ones in OUTLET_KEYS describe
 * a single outlet (its address, hours, which ways of ordering it offers) and
 * are stored per outlet; reads return the values of the outlet the request is
 * working in (see OutletContext).
 */
class Settings
{
    public const OUTLET_KEYS = [
        'shop.address', 'shop.phone', 'shop.whatsapp', 'shop.maps_url', 'shop.counter',
        'hours.open', 'hours.close', 'hours.last_order',
        'ordering.enabled', 'ordering.pickup_enabled', 'ordering.delivery_enabled', 'ordering.dine_in_enabled',
        'ordering.pickup_eta_min', 'ordering.pickup_eta_max', 'ordering.queue_prefix',
    ];

    private const CACHE_KEY = 'shop.settings';

    /** @var array<string, mixed>|null */
    private ?array $global = null;

    /** @var array<int, array<string, mixed>> */
    private array $outlets = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key, '');
    }

    /**
     * A business-wide setting, read without looking at the current outlet.
     */
    public function global(string $key, mixed $default = null): mixed
    {
        return $this->globals()[$key] ?? $default;
    }

    /**
     * Every setting as it applies to the outlet the request is working in.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [...$this->globals(), ...$this->forOutlet(app(OutletContext::class)->current())];
    }

    /**
     * The per-outlet settings of one outlet, defaults included.
     *
     * @return array<string, mixed>
     */
    public function forOutlet(Outlet $outlet): array
    {
        return $this->outlets[$outlet->id] ??= [
            ...array_intersect_key(config('shop.defaults', []), array_flip(self::OUTLET_KEYS)),
            ...Cache::rememberForever(
                self::CACHE_KEY.'.outlet.'.$outlet->id,
                fn () => DB::table('outlet_settings')->where('outlet_id', $outlet->id)->pluck('value', 'key')
                    ->map(fn ($value) => json_decode($value, true))->all(),
            ),
        ];
    }

    /**
     * Persist the given settings. Business-wide keys are saved globally;
     * outlet keys are saved for $outlet, or for the outlet the request is
     * working in. Unknown keys are ignored.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values, ?Outlet $outlet = null): void
    {
        $known = array_intersect_key($values, config('shop.defaults', []));
        $forOutlet = array_intersect_key($known, array_flip(self::OUTLET_KEYS));

        foreach (array_diff_key($known, $forOutlet) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if ($forOutlet !== []) {
            $outlet ??= app(OutletContext::class)->current();

            foreach ($forOutlet as $key => $value) {
                DB::table('outlet_settings')->updateOrInsert(
                    ['outlet_id' => $outlet->id, 'key' => $key],
                    ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
                );
            }

            Cache::forget(self::CACHE_KEY.'.outlet.'.$outlet->id);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->global = null;
        $this->outlets = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function globals(): array
    {
        return $this->global ??= [
            ...array_diff_key(config('shop.defaults', []), array_flip(self::OUTLET_KEYS)),
            ...Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        ];
    }
}
