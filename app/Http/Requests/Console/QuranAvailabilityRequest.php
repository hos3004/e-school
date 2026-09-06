<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuranAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $protected = ['organization_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'staff_profile_id' => ['prohibited']];
        if ($this->routeIs('console.availability.decide')) {
            return [...$protected, 'decision' => ['required', Rule::in(['approved', 'rejected'])]];
        }
        if ($this->isMethod('DELETE')) {
            return $protected;
        }

        return [...$protected,
            'weekday' => ['required', 'integer', 'between:0,6'], 'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'timezone' => ['required', 'timezone'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'weekday' => __('console_quran.weekday'), 'start_time' => __('console_quran.start_time'), 'end_time' => __('console_quran.end_time'),
            'timezone' => __('console_quran.timezone'), 'effective_from' => __('console_quran.starts_on'), 'effective_to' => __('console_quran.ends_on'),
            'decision' => __('console_quran.availability_decision'),
        ];
    }
}
