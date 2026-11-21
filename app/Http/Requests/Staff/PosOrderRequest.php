<?php

namespace App\Http\Requests\Staff;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\DiningTable;
use App\Rules\MalaysianMobile;
use App\Support\OutletContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shape only. Whether each line can be sold, and what it costs, is
     * decided by CartPricer against the live menu.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([OrderType::Pickup->value, OrderType::DineIn->value])],
            'dining_table_id' => ['nullable', Rule::exists(DiningTable::class, 'id')->where('is_active', true)->where('outlet_id', app(OutletContext::class)->current()->id)],
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', new MalaysianMobile],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid' => ['required', 'boolean'],
            'tendered' => ['nullable', 'integer', 'min:0', 'max:10000000'],

            'lines' => ['required', 'array', 'min:1', 'max:60'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.modifier_ids' => ['array', 'max:20'],
            'lines.*.modifier_ids.*' => ['integer'],
            'lines.*.combo' => ['boolean'],
            'lines.*.combo_choice' => ['nullable', 'string', 'max:100'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'lines.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item to the order.',
        ];
    }

    /**
     * Cart lines in the shape CartPricer expects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return array_map(fn (array $line, int $index) => [
            'id' => 'pos-'.$index,
            'product_id' => (int) $line['product_id'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'modifier_ids' => array_map('intval', $line['modifier_ids'] ?? []),
            'combo' => (bool) ($line['combo'] ?? false),
            'combo_choice' => $line['combo_choice'] ?? null,
            'quantity' => (int) $line['quantity'],
            'notes' => filled($line['notes'] ?? null) ? trim((string) $line['notes']) : null,
        ], $this->validated('lines'), array_keys($this->validated('lines')));
    }
}
