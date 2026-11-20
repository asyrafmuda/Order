<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A mobile number may belong to one account only, because customers sign in
 * with it. Compared after normalising ("012-345 6789" = "60123456789").
 */
class UniquePhone implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = User::query()
            ->where('phone', Phone::normalize((string) $value))
            ->when($this->ignoreUserId, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($taken) {
            $fail('This mobile number already has an account.');
        }
    }
}
