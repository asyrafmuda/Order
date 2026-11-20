<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // A mobile number (customers) or an email address (staff, or customers who added one).
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login')->toString());

        $credentials = [
            ...(str_contains($login, '@') ? ['email' => Str::lower($login)] : ['phone' => Phone::normalize($login)]),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->accountKey(), 900);

            throw ValidationException::withMessages(['login' => 'That mobile number / email and PIN do not match our records.']);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        // Five tries per device, and ten per account from anywhere: a 6-digit
        // PIN must not be guessable by spreading attempts across addresses.
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->accountKey(), 10) => $this->accountKey(),
            default => null,
        };

        if ($key === null) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($key),
                'minutes' => ceil(RateLimiter::availableIn($key) / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return $this->accountKey().'|'.$this->ip();
    }

    private function accountKey(): string
    {
        $login = trim($this->string('login')->toString());

        return 'login|'.Str::transliterate(str_contains($login, '@') ? Str::lower($login) : Phone::normalize($login));
    }
}
