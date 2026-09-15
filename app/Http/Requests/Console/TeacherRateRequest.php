<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\RateScope;

/** تسجيل سعر حصة المعلم من تاريخ سريان — النطاق يحدد ما يلزم من مفاتيح. */
final class TeacherRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.contract.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scope = RateScope::tryFrom($this->string('scope')->toString());

        return [
            'scope' => ['required', 'string', Rule::enum(RateScope::class)],
            // البرنامج يُستنتج من الكورس في نطاق الكورس، فلا يُطلب من العميل.
            'program_id' => [
                Rule::requiredIf(fn (): bool => $scope === RateScope::Program),
                'nullable', 'ulid',
            ],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $scope === RateScope::Course),
                'nullable', 'ulid',
            ],
            'session_type' => [
                Rule::requiredIf(fn (): bool => $scope === RateScope::SessionType),
                'nullable', Rule::in(['individual', 'group']),
            ],
            'amount_major' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function scope(): RateScope
    {
        return RateScope::from((string) $this->validated('scope'));
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'scope' => __('console_people.rates.scope'),
            'program_id' => __('console_people.rates.program'),
            'course_id' => __('console_people.rates.course'),
            'session_type' => __('console_people.rates.session_type'),
            'amount_major' => __('console_people.rates.amount'),
            'effective_from' => __('console_people.rates.effective_from'),
            'reason' => __('console_people.rates.reason'),
        ];
    }
}
