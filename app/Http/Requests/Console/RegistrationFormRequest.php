<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Students\Domain\Enums\RegistrationQuestionType;

final class RegistrationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('student.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'], 'actor_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('registration_forms', 'slug')->ignore($this->route('form'))],
            'is_active' => ['required', 'boolean'],
            'preferred_program_id' => ['nullable', 'ulid', 'required_with:preferred_course_id'],
            'preferred_course_id' => ['nullable', 'ulid', 'required_with:preferred_program_id'],
            'questions' => ['present', 'array'],
            'questions.*' => ['array:id,question,type,options,is_required,is_active,is_filterable'],
            'questions.*.id' => ['nullable', 'ulid', 'distinct'],
            'questions.*.question' => ['required', 'string', 'max:1000'],
            'questions.*.type' => ['required', Rule::enum(RegistrationQuestionType::class)],
            'questions.*.options' => ['present', 'array', 'max:20'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.is_required' => ['required', 'boolean'],
            'questions.*.is_active' => ['required', 'boolean'],
            'questions.*.is_filterable' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = $this->input('questions', []);
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $type = RegistrationQuestionType::tryFrom(is_string($row['type'] ?? null) ? $row['type'] : '');
                if ($type?->hasOptions() && (!is_array($row['options'] ?? null) || count(array_unique($row['options'], SORT_REGULAR)) < 2)) {
                    $validator->errors()->add('questions.'.$index.'.options', __('console_registration.minimum_options'));
                }
                if ($type !== null && !$type->canBeFiltered() && ($row['is_filterable'] ?? false)) {
                    $validator->errors()->add('questions.'.$index.'.is_filterable', __('console_registration.filter_type'));
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => __('console_registration.fields.title'), 'description' => __('console_registration.fields.description'),
            'slug' => __('console_registration.fields.slug'), 'preferred_course_id' => __('console_registration.fields.course'),
            'preferred_program_id' => __('console_registration.fields.program'),
            'questions.*.question' => __('console_registration.fields.question'),
            'questions.*.options' => __('console_registration.fields.options'),
        ];
    }
}
