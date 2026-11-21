<?php

namespace App\LicenseServer\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * An update package (the ZIP made by scripts/package.sh) that customers'
 * sites can install from Admin → System Update.
 */
#[Fillable(['product', 'version', 'notes', 'path', 'size', 'sha256', 'audience', 'published_at'])]
class Release extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_SELECTED = 'selected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<License, $this> */
    public function licenses(): BelongsToMany
    {
        return $this->belongsToMany(License::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Whether this release is meant for the license at all (ignoring its
     * update period).
     */
    public function isFor(License $license): bool
    {
        return $this->isPublished()
            && $this->product === $license->product
            && ($this->audience === self::AUDIENCE_ALL || $this->licenses()->whereKey($license->id)->exists());
    }

    /**
     * Whether the license's update period covers this release.
     */
    public function isCoveredBy(License $license): bool
    {
        return $license->updates_until === null
            || ($this->published_at !== null && $this->published_at->lte($license->updates_until->copy()->endOfDay()));
    }

    /**
     * Published releases meant for the license, newest version first.
     *
     * @return Collection<int, Release>
     */
    public static function forLicense(License $license): Collection
    {
        return static::query()
            ->where('product', $license->product)
            ->whereNotNull('published_at')
            ->where(fn (Builder $query) => $query
                ->where('audience', self::AUDIENCE_ALL)
                ->orWhereHas('licenses', fn (Builder $licenses) => $licenses->whereKey($license->id)))
            ->get()
            ->sort(fn (Release $a, Release $b) => version_compare($b->version, $a->version))
            ->values();
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function fileName(): string
    {
        return "{$this->product}-{$this->version}.zip";
    }
}
