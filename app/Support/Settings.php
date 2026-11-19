<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime shop settings: config/shop.php defaults overridden by rows in the
 * "settings" table. Resolved as a singleton and cached between requests.
 */
class Settings
{
    private const CACHE_KEY = 'shop.settings';

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

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
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resolved ??= array_merge(
            config('shop.defaults', []),
            Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        );
    }

    /**
     * Persist the given settings. Unknown keys are ignored.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $known = array_intersect_key($values, config('shop.defaults', []));

        foreach ($known as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
