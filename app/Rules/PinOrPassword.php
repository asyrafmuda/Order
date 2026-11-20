<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Customers may protect their account with a 6-digit PIN; anything else must
 * meet the normal password rules.
 */
class PinOrPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) $value;

        if (preg_match('/^\d{1,7}$/', $secret)) {
            (new Pin)->validate($attribute, $secret, $fail);

            return;
        }

        $validator = Validator::make([$attribute => $secret], [$attribute => [Password::defaults()]]);

        if ($validator->fails()) {
            $fail($validator->errors()->first($attribute));
        }
    }
}
