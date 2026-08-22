<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Organization;

final class StoreAcademicCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        return $organization !== null
            && (bool) $this->user()?->can('create', AcademicCalendar::class);
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
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('organization::validation.calendar_name_required'),
            'starts_on.required' => __('organization::validation.date_required'),
            'ends_on.after' => __('organization::errors.calendar_range_invalid'),
        ];
    }
}
