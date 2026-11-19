<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fee and minimum order arrive as integer sen.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'areas' => ['nullable', 'string', 'max:255'],
            'fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_order' => ['required', 'integer', 'min:0', 'max:10000000'],
            'eta_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'eta_minutes' => 'delivery time',
            'min_order' => 'minimum order',
        ];
    }
}
