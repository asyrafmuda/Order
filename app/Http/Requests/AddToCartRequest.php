<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shape only: whether the product can be sold with these options is
     * decided by the cart against the live menu.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'modifier_ids' => ['array', 'max:20'],
            'modifier_ids.*' => ['integer'],
            'combo' => ['boolean'],
            'combo_choice' => ['nullable', 'string', 'max:100'],
            'quantity' => ['integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:200'],
        ];
    }
}
