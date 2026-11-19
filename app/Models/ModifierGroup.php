<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'min_select', 'max_select', 'sort_order'])]
class ModifierGroup extends Model
{
    /** @return HasMany<Modifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
