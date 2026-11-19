<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait GeneratesUniqueSlugs
{
    /**
     * A URL slug for the given name that no other row of the model uses
     * ("smash-burger", "smash-burger-2", ...). Soft-deleted rows still hold
     * their slug because the column is unique.
     *
     * @param  class-string<Model>  $model
     */
    protected function uniqueSlug(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        $exists = function (string $candidate) use ($model, $ignoreId): bool {
            $query = $model::query();

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withTrashed();
            }

            return $query
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();
        };

        while ($exists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
