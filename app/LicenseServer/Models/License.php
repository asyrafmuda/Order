<?php

namespace App\LicenseServer\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase code sold to one customer.
 */
#[Fillable(['code', 'product', 'customer_name', 'customer_email', 'max_activations', 'updates_until', 'notes', 'revoked_at', 'revoked_reason'])]
class License extends Model
{
    /** No 0/O or 1/I, so codes read out over the phone are not mistaken. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_activations' => 'integer',
            'updates_until' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public static function newCode(): string
    {
        do {
            $groups = [];

            for ($group = 0; $group < 4; $group++) {
                $chars = '';

                for ($i = 0; $i < 4; $i++) {
                    $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }

                $groups[] = $chars;
            }

            $code = implode('-', $groups);
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /** @return HasMany<Activation, $this> */
    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class)->latest('activated_at');
    }

    /** @return HasMany<Activation, $this> */
    public function activeActivations(): HasMany
    {
        return $this->hasMany(Activation::class)->whereNull('deactivated_at');
    }

    /** @return BelongsToMany<Release, $this> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @param  Builder<License>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(fn (Builder $inner) => $inner
            ->where('code', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhereHas('activations', fn (Builder $activation) => $activation->where('domain', 'like', $like)));
    }
}
