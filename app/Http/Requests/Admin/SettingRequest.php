<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Outlet;
use App\Support\BrandPalette;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
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
        $rules = [
            'outlets.multi_enabled' => ['required', 'boolean'],

            'shop.name' => ['required', 'string', 'max:80'],
            'shop.tagline' => ['nullable', 'string', 'max:80'],
            'shop.address' => ['required', 'string', 'max:255'],
            'shop.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'shop.whatsapp' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'shop.maps_url' => ['nullable', 'url', 'max:255'],
            'shop.counter' => ['required', 'string', 'max:40'],

            'brand.icon' => ['required', 'string', 'max:8'],
            'brand.theme' => ['required', Rule::in(BrandPalette::THEMES)],
            'brand.primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand.accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand.footer_text' => ['nullable', 'string', 'max:255'],
            'brand.badge_text' => ['nullable', 'string', 'max:60'],

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
            'ordering.auto_close_hours' => ['required', 'integer', 'min:0', 'max:72'],

            'payments.fpx_enabled' => ['required', 'boolean'],
            'payments.card_enabled' => ['required', 'boolean'],
            'payments.ewallet_enabled' => ['required', 'boolean'],
            'payments.cash_enabled' => ['required', 'boolean'],
            'payments.cash_requires_account' => ['required', 'boolean'],
            'payments.cash_max_amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'payments.dine_in_pay_at_counter' => ['required', 'boolean'],

            'pos.quick_notes' => ['present', 'array', 'max:12'],
            'pos.quick_notes.*' => ['nullable', 'string', 'max:30'],

            'receipt.paper' => ['required', Rule::in(['58', '80'])],
            'receipt.header' => ['nullable', 'string', 'max:80'],
            'receipt.footer' => ['nullable', 'string', 'max:160'],
            'receipt.auto_print' => ['required', 'boolean'],
            'receipt.kitchen_slip' => ['required', 'boolean'],

            'tax.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax.inclusive' => ['required', 'boolean'],

            'notifications.enabled' => ['required', 'boolean'],
        ];

        // With several outlets, each outlet's address, hours and ways of
        // ordering are edited under Outlets, so this form does not send them.
        if (app(OutletContext::class)->multi()) {
            foreach (Settings::OUTLET_KEYS as $key) {
                $rules[$key] = ['exclude'];
            }
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $multi = app(OutletContext::class)->multi();

                $anyChannel = $multi
                    || $this->boolean('ordering.pickup_enabled')
                    || $this->boolean('ordering.delivery_enabled')
                    || $this->boolean('ordering.dine_in_enabled');

                // Switching multi-outlet off folds everything back into the main
                // outlet, which would hide other outlets' unfinished orders.
                if ($multi && ! $this->boolean('outlets.multi_enabled')) {
                    $open = Order::query()
                        ->whereIn('status', [OrderStatus::PendingPayment, ...OrderStatus::active()])
                        ->where('outlet_id', '!=', Outlet::default()->id)
                        ->count();

                    if ($open > 0) {
                        $validator->errors()->add('outlets.multi_enabled', "Other outlets still have {$open} open order(s). Finish or cancel them before switching multi-outlet off.");
                    }
                }

                if (! $anyChannel) {
                    $validator->errors()->add('ordering.delivery_enabled', 'Keep at least one of pickup, delivery or dine-in switched on.');
                }

                // Buttons put white text on the main colour, and the accent is
                // used for prices on the dark background: both must stay readable.
                if ($validator->errors()->missing('brand.primary_color') && BrandPalette::contrast((string) $this->input('brand.primary_color'), '#ffffff') < 3) {
                    $validator->errors()->add('brand.primary_color', 'This colour is too light for white button text. Pick a darker or stronger one.');
                }

                // On the dark theme the accent is used as it is for prices, so it must be light
                // enough to read there. The light theme darkens it by itself where needed.
                $dark = $this->input('brand.theme') !== BrandPalette::LIGHT;

                if ($dark && $validator->errors()->missing('brand.accent_color') && BrandPalette::contrast((string) $this->input('brand.accent_color'), BrandPalette::SURFACES[BrandPalette::DARK]) < 4.5) {
                    $validator->errors()->add('brand.accent_color', 'This colour is too dark to read on the dark theme. Pick a lighter one, or switch to the light theme.');
                }

                $anyPayment = collect(['fpx', 'card', 'ewallet', 'cash'])
                    ->contains(fn (string $method) => $this->boolean("payments.{$method}_enabled"));

                if (! $anyPayment) {
                    $validator->errors()->add('payments.cash_enabled', 'Keep at least one payment method switched on.');
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
            'brand.icon' => 'icon',
            'brand.theme' => 'theme',
            'brand.primary_color' => 'main colour',
            'brand.accent_color' => 'accent colour',
            'brand.footer_text' => 'footer text',
            'brand.badge_text' => 'badge',
            'hours.open' => 'opening time',
            'hours.close' => 'closing time',
            'hours.last_order' => 'last order time',
            'ordering.pickup_eta_min' => 'minimum prep time',
            'ordering.pickup_eta_max' => 'maximum prep time',
            'ordering.queue_prefix' => 'queue prefix',
            'ordering.auto_close_hours' => 'auto-close time',
            'payments.cash_max_amount' => 'cash order limit',
            'pos.quick_notes.*' => 'quick note',
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
        $validated = $this->validated();

        // Lists are stored whole, not as "pos.quick_notes.0", "…1".
        $quickNotes = array_values(array_unique(array_filter(array_map('trim', $validated['pos']['quick_notes'] ?? []))));
        unset($validated['pos']['quick_notes']);

        $settings = Arr::dot($validated);
        $settings['pos.quick_notes'] = $quickNotes;

        $booleans = [
            'outlets.multi_enabled', 'ordering.enabled', 'ordering.pickup_enabled', 'ordering.delivery_enabled', 'ordering.dine_in_enabled',
            'tax.inclusive', 'notifications.enabled', 'payments.dine_in_pay_at_counter',
            'payments.fpx_enabled', 'payments.card_enabled', 'payments.ewallet_enabled', 'payments.cash_enabled',
            'payments.cash_requires_account',
        ];

        foreach ($booleans as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $settings['brand.primary_color'] = strtolower($settings['brand.primary_color']);
        $settings['brand.accent_color'] = strtolower($settings['brand.accent_color']);
        $settings['brand.footer_text'] ??= '';
        $settings['brand.badge_text'] ??= '';
        $settings['ordering.auto_close_hours'] = (int) $settings['ordering.auto_close_hours'];
        $settings['payments.cash_max_amount'] = (int) $settings['payments.cash_max_amount'];

        if (array_key_exists('ordering.queue_prefix', $settings)) {
            $settings['ordering.pickup_eta_min'] = (int) $settings['ordering.pickup_eta_min'];
            $settings['ordering.pickup_eta_max'] = (int) $settings['ordering.pickup_eta_max'];
            $settings['ordering.queue_prefix'] = strtoupper($settings['ordering.queue_prefix']);
        }
        $settings['tax.rate'] = (float) $settings['tax.rate'];

        return $settings;
    }
}
