<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Malaysian mobile number in any common format: "012-345 6789",
 * "0123456789", "+60 12-345 6789" or "60123456789". It is stored as
 * "60123456789" (see Phone::normalize), so all of them sign in the same.
 */
class MalaysianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[^0-9\s\-+()]/', $value) || ! Phone::isMobile($value)) {
            $fail('Enter a Malaysian mobile number, e.g. 012-345 6789.');
        }
    }
}
