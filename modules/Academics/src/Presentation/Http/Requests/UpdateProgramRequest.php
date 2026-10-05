<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\TargetGender;

/**
 * طلب تحديث برنامج أكاديمي.
 */
final class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('program'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $programId = (string) $this->route('program')?->getKey();

        return [
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'unique:programs,code,'.$programId,
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['sometimes', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:2000'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
            'default_session_minutes' => ['sometimes', 'integer', 'min:'.(int) config('academics.session_minutes.minimum')],
            'default_rate' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'program_type' => ['sometimes', Rule::enum(ProgramType::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_gender' => ['sometimes', Rule::enum(TargetGender::class)],
            'age_from' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum')],
            'age_to' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum'), 'gte:age_from'],
            'objectives' => ['nullable', 'array'],
            'language' => ['nullable', 'string', 'max:16'],
            'eligibility' => ['nullable', 'array'],
            'eligibility.countries' => ['sometimes', 'array'],
            'eligibility.countries.*' => ['string', 'size:26', 'distinct'],
            'eligibility.regions' => ['sometimes', 'array'],
            'eligibility.regions.*' => ['string', 'size:26', 'distinct'],
            'eligibility.age_from' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum')],
            'eligibility.age_to' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum'), 'gte:eligibility.age_from'],
            'eligibility.gender' => ['nullable', Rule::enum(TargetGender::class)],
            'eligibility.manual_approval_required' => ['sometimes', 'boolean'],
            'eligibility.teacher_gender_rule' => ['sometimes', Rule::in(['any', 'same', 'opposite'])],
            'eligibility.requires_individual_sessions' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:'.(int) config('academics.reason.minimum_length'), 'max:'.(int) config('academics.reason.maximum_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('academics::errors.program_code_taken'),
        ];
    }
}
