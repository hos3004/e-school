<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;

final class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        return $organization !== null
            && (bool) $this->user()?->can('create', Holiday::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'academic_calendar_id' => [
                'nullable',
                'string',
                Rule::exists('academic_calendars', 'id'),
            ],
            'blocks_scheduling' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('organization::validation.holiday_name_required'),
            'starts_on.required' => __('organization::validation.date_required'),
            'ends_on.after_or_equal' => __('organization::validation.holiday_end_before_start'),
            'academic_calendar_id.exists' => __('organization::validation.calendar_not_found'),
        ];
    }
}
