<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\Locales;

/**
 * طلب تسجيل طالب جديد.
 */
final class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StudentProfile::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'user_id' => ['required', 'string', 'size:26', 'unique:student_profiles,user_id'],
            'student_code' => [
                'required',
                'string',
                'max:32',
                'unique:student_profiles,student_code',
                'regex:/^[A-Za-z0-9-]+$/',
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', 'string', Rule::in(Locales::supported())],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => __('students::validation.user_already_student'),
            'student_code.unique' => __('students::validation.code_taken'),
            'date_of_birth.before' => __('students::validation.birth_before_today'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'organization_id',
            'user_id',
            'student_code',
            'date_of_birth',
            'gender',
            'nationality',
            'country',
            'city',
            'preferred_language',
            'joined_at',
            'notes',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('students::attributes.'.$field)],
        )->all();
    }
}
