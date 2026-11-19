<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModifierGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'min_select' => ['required', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],

            'modifiers' => ['required', 'array', 'min:1', 'max:30'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'modifiers.*.price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'modifiers.*.is_available' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_select' => 'minimum choices',
            'max_select' => 'maximum choices',
            'modifiers' => 'options',
            'modifiers.*.name' => 'option name',
            'modifiers.*.price' => 'option price',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modifiers.required' => 'Add at least one option to the group.',
            'max_select.gte' => 'Maximum choices cannot be lower than the minimum.',
        ];
    }
}
