<?php

namespace App\Http\Requests\Staff;

use App\Models\Rider;
use App\Support\OutletContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AssignRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A null rider_id takes the delivery back from its rider.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['present', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                // Riders deliver for the outlet they work at.
                if (! Rider::query()->forOutlet(app(OutletContext::class)->current())->whereKey($value)->exists()) {
                    $fail('Choose a rider from this outlet.');
                }
            }],
        ];
    }

    public function rider(): ?Rider
    {
        $riderId = $this->validated('rider_id');

        return $riderId ? Rider::query()->forOutlet(app(OutletContext::class)->current())->with('user')->findOrFail($riderId) : null;
    }
}
