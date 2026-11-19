<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
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
        $delivery = app(Cart::class)->type() === OrderType::Delivery;
        $phone = 'regex:/^\+?[0-9\s\-]{9,16}$/';

        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', $phone],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class)->except($delivery ? [PaymentMethod::Cash] : []),
            ],

            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_phone' => ['nullable', 'string', $phone],
            'line1' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:80'],
            'postcode' => [Rule::requiredIf($delivery), 'nullable', 'digits:5'],
            'delivery_notes' => ['nullable', 'string', 'max:255'],
            'save_address' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'name',
            'customer_phone' => 'mobile number',
            'customer_email' => 'email',
            'line1' => 'address',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_phone.regex' => 'Enter a valid mobile number, e.g. 012-345 6789.',
            'recipient_phone.regex' => 'Enter a valid mobile number, e.g. 012-345 6789.',
            'payment_method.enum' => 'Please choose an available payment method.',
        ];
    }
}
