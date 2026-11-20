<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\PinOrPassword;
use App\Rules\UniquePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');
        $customer = $this->input('role') === UserRole::Customer->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            // Customers sign in with their mobile number; everyone else with an email.
            'email' => [$customer ? 'nullable' : 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'phone' => [$customer ? 'required' : 'nullable', 'string', 'regex:/^\+?[0-9\s\-]{9,16}$/', new UniquePhone($user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', $customer ? new PinOrPassword : Password::defaults()],
        ];
    }

    /**
     * An admin must not lock themselves out by demoting or deactivating
     * their own account.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User|null $user */
                $user = $this->route('user');

                if (! $user || ! $user->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }
            },
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
