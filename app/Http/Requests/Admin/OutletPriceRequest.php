<?php

namespace App\Http\Requests\Admin;

use App\Support\OutletPrices;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The prices one outlet charges. Each row names a menu item and either the
 * outlet's price in sen, or null to follow the standard price.
 */
class OutletPriceRequest extends FormRequest
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
            'prices' => ['present', 'array', 'max:5000'],
            'prices.*.type' => ['required', Rule::in(OutletPrices::TYPES)],
            'prices.*.id' => ['required', 'integer'],
            'prices.*.price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Every row must point at something that is on the menu.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $standard = app(OutletPrices::class)->standardPrices();

                foreach ($this->input('prices', []) as $index => $row) {
                    if (! isset($standard[$row['type']][(int) $row['id']])) {
                        $validator->errors()->add("prices.{$index}.price", 'This item is no longer on the menu. Reload the page and try again.');
                    }
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
            'prices.*.price.integer' => 'Enter a valid price.',
            'prices.*.price.min' => 'A price cannot be negative.',
            'prices.*.price.max' => 'That price is too high.',
        ];
    }

    /**
     * @return array<int, array{type: string, id: int, price: int|null}>
     */
    public function rows(): array
    {
        return array_map(fn (array $row) => [
            'type' => $row['type'],
            'id' => (int) $row['id'],
            'price' => isset($row['price']) ? (int) $row['price'] : null,
        ], $this->validated('prices'));
    }
}
