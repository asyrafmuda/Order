<?php

namespace App\LicenseServer\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One domain a purchase code is (or was) used on.
 */
#[Fillable(['license_id', 'domain', 'version', 'ip', 'activated_at', 'last_checked_at', 'deactivated_at'])]
class Activation extends Model
{
    protected $table = 'license_activations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<License, $this> */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
