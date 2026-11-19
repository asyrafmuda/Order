<?php

namespace App\Http\Requests\Staff;

use App\Enums\KitchenItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKitchenItemRequest extends FormRequest
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
            'status' => ['required', Rule::enum(KitchenItemStatus::class)],
        ];
    }

    public function status(): KitchenItemStatus
    {
        return KitchenItemStatus::from($this->validated('status'));
    }
}
