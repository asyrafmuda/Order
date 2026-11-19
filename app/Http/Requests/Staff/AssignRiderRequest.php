<?php

namespace App\Http\Requests\Staff;

use App\Models\Rider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'rider_id' => ['present', 'nullable', 'integer', Rule::exists(Rider::class, 'id')],
        ];
    }

    public function rider(): ?Rider
    {
        $riderId = $this->validated('rider_id');

        return $riderId ? Rider::query()->with('user')->findOrFail($riderId) : null;
    }
}
