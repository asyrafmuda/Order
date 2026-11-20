<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\Pin;
use App\Rules\UniquePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{9,16}$/', new UniquePhone],
            'pin' => ['required', 'string', new Pin],
            // Optional: lets the customer reset a forgotten PIN by email.
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 012-345 6789.',
        ];
    }
}
