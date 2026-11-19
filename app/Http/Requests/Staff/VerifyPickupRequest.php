<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPickupRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * What the cashier typed or scanned, normalised: "#a001 " → "A001".
     */
    public function code(): string
    {
        return strtoupper(ltrim(trim($this->validated('code')), '#'));
    }
}
