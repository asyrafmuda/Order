<?php

namespace App\Http\Requests\Admin;

use App\Models\Rider;
use App\Models\User;
use App\Rules\UniquePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RiderRequest extends FormRequest
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
        /** @var Rider|null $rider */
        $rider = $this->route('rider');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($rider?->user_id)],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{9,16}$/', new UniquePhone($rider?->user_id)],
            'password' => [$rider ? 'nullable' : 'required', Password::defaults()],
            'vehicle_type' => ['required', 'string', 'max:40'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'is_available' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
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
