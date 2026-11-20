<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A 6-digit PIN that is not one of the handful everybody tries first.
 */
class Pin implements ValidationRule
{
    private const TOO_EASY = ['123456', '654321', '123123', '112233', '121212'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! preg_match('/^\d{6}$/', $pin)) {
            $fail('Your PIN must be exactly 6 digits.');

            return;
        }

        if (in_array($pin, self::TOO_EASY, true) || count(array_unique(str_split($pin))) === 1) {
            $fail('That PIN is too easy to guess. Please choose another.');
        }
    }
}
