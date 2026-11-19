<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Settings are submitted as nested groups ({shop: {name: ...}, hours: {...}})
 * that mirror the dotted keys in config/shop.php.
 */
class SettingRequest extends FormRequest
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
            'shop.name' => ['required', 'string', 'max:80'],
            'shop.tagline' => ['nullable', 'string', 'max:80'],
            'shop.address' => ['required', 'string', 'max:255'],
            'shop.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'shop.whatsapp' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'shop.maps_url' => ['nullable', 'url', 'max:255'],
            'shop.counter' => ['required', 'string', 'max:40'],

            'hours.open' => ['required', 'date_format:H:i'],
            'hours.close' => ['required', 'date_format:H:i'],
            'hours.last_order' => ['required', 'date_format:H:i'],

            'ordering.enabled' => ['required', 'boolean'],
            'ordering.pickup_enabled' => ['required', 'boolean'],
            'ordering.delivery_enabled' => ['required', 'boolean'],
            'ordering.pickup_eta_min' => ['required', 'integer', 'min:1', 'max:180'],
            'ordering.pickup_eta_max' => ['required', 'integer', 'max:240', 'gte:ordering.pickup_eta_min'],
            'ordering.queue_prefix' => ['required', 'string', 'alpha', 'max:3'],

            'tax.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax.inclusive' => ['required', 'boolean'],

            'notifications.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('ordering.pickup_enabled') && ! $this->boolean('ordering.delivery_enabled')) {
                    $validator->errors()->add('ordering.delivery_enabled', 'Keep at least one of pickup or delivery switched on.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shop.name' => 'shop name',
            'shop.tagline' => 'tagline',
            'shop.address' => 'address',
            'shop.phone' => 'phone',
            'shop.whatsapp' => 'WhatsApp number',
            'shop.maps_url' => 'map link',
            'shop.counter' => 'pickup counter',
            'hours.open' => 'opening time',
            'hours.close' => 'closing time',
            'hours.last_order' => 'last order time',
            'ordering.pickup_eta_min' => 'minimum prep time',
            'ordering.pickup_eta_max' => 'maximum prep time',
            'ordering.queue_prefix' => 'queue prefix',
            'tax.rate' => 'SST rate',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop.whatsapp.regex' => 'Use digits only in international format, e.g. 60123456789.',
            'ordering.pickup_eta_max.gte' => 'Maximum prep time cannot be lower than the minimum.',
        ];
    }

    /**
     * The validated input as flat "group.key" settings with native types.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = Arr::dot($this->validated());

        foreach (['ordering.enabled', 'ordering.pickup_enabled', 'ordering.delivery_enabled', 'tax.inclusive', 'notifications.enabled'] as $key) {
            $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
        }

        $settings['ordering.pickup_eta_min'] = (int) $settings['ordering.pickup_eta_min'];
        $settings['ordering.pickup_eta_max'] = (int) $settings['ordering.pickup_eta_max'];
        $settings['ordering.queue_prefix'] = strtoupper($settings['ordering.queue_prefix']);
        $settings['tax.rate'] = (float) $settings['tax.rate'];

        return $settings;
    }
}
