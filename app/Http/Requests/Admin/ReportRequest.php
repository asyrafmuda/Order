<?php

namespace App\Http\Requests\Admin;

use App\Support\OutletContext;
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
            'outlet' => ['nullable', 'in:all'],
        ];
    }

    /**
     * Whether the report covers every outlet at once. Only a super admin of
     * a multi-outlet business can ask for that; everyone else gets the
     * outlet they are working in.
     */
    public function allOutlets(): bool
    {
        return $this->validated('outlet') === 'all'
            && app(OutletContext::class)->multi()
            && (bool) $this->user()?->isAdmin();
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
