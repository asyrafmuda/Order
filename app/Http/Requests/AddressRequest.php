<?php

namespace App\Http\Requests;

use App\Models\DeliveryZone;
use App\Rules\MalaysianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $address = $this->route('address');

        return $address ? $this->user()->can('update', $address) : true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:40'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new MalaysianMobile],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:80'],
            'postcode' => ['required', 'digits:5'],
            'delivery_zone_id' => ['nullable', Rule::exists(DeliveryZone::class, 'id')],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }
}
