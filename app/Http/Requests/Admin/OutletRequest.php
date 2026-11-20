<?php

namespace App\Http\Requests\Admin;

use App\Models\Outlet;
use App\Support\OutletContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * An outlet's identity plus the settings that belong to it. Used when a
 * super admin adds or edits an outlet and when a manager edits their own.
 */
class OutletRequest extends FormRequest
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
        $outlet = $this->outlet();

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique(Outlet::class)->ignore($outlet?->id)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],

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
            'ordering.dine_in_enabled' => ['required', 'boolean'],
            'ordering.pickup_eta_min' => ['required', 'integer', 'min:1', 'max:180'],
            'ordering.pickup_eta_max' => ['required', 'integer', 'max:240', 'gte:ordering.pickup_eta_min'],
            'ordering.queue_prefix' => ['required', 'string', 'alpha', 'max:3'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $anyChannel = $this->boolean('ordering.pickup_enabled')
                    || $this->boolean('ordering.delivery_enabled')
                    || $this->boolean('ordering.dine_in_enabled');

                if (! $anyChannel) {
                    $validator->errors()->add('ordering.delivery_enabled', 'Keep at least one of pickup, delivery or dine-in switched on.');
                }

                $outlet = $this->outlet();

                if ($outlet?->is_default && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'The main outlet cannot be deactivated.');
                }
            },
        ];
    }

    /**
     * The outlet being edited: the one in the URL, or the outlet the request
     * is working in when a manager saves their own. Null when adding one.
     */
    private function outlet(): ?Outlet
    {
        return $this->route('outlet') ?? ($this->routeIs('admin.outlet.update') ? app(OutletContext::class)->current() : null);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
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
     * The outlet's own settings as flat "group.key" values with native types.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = Arr::dot(Arr::only($this->validated(), ['shop', 'hours', 'ordering']));

        foreach (['ordering.enabled', 'ordering.pickup_enabled', 'ordering.delivery_enabled', 'ordering.dine_in_enabled'] as $key) {
            $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
        }

        $settings['ordering.pickup_eta_min'] = (int) $settings['ordering.pickup_eta_min'];
        $settings['ordering.pickup_eta_max'] = (int) $settings['ordering.pickup_eta_max'];
        $settings['ordering.queue_prefix'] = strtoupper($settings['ordering.queue_prefix']);
        $settings['shop.maps_url'] ??= '';

        return $settings;
    }
}
