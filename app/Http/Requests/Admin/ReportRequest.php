<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
        ];
    }

    /**
     * The reporting period, defaulting to the last 7 days and capped at a year.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $to = CarbonImmutable::parse($this->validated('to') ?? 'today')->startOfDay();
        $from = CarbonImmutable::parse($this->validated('from') ?? $to->subDays(6))->startOfDay();

        if ($from->greaterThan($to)) {
            $from = $to;
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        return [$from, $to];
    }
}
