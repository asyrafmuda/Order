<?php

namespace App\Models\Concerns;

use App\Models\Outlet;
use App\Support\OutletContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that live in one outlet. New records are placed in the outlet
 * the request is working in unless one is given.
 *
 * Reads are scoped explicitly with ->forOutlet($outlet): there is deliberately
 * no global scope, so jobs, commands and public order links are never
 * silently filtered.
 */
trait BelongsToOutlet
{
    public static function bootBelongsToOutlet(): void
    {
        static::creating(function (self $model) {
            $model->outlet_id ??= app(OutletContext::class)->current()->id;
        });
    }

    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForOutlet(Builder $query, Outlet|int $outlet): void
    {
        $query->where($this->qualifyColumn('outlet_id'), $outlet instanceof Outlet ? $outlet->id : $outlet);
    }
}
