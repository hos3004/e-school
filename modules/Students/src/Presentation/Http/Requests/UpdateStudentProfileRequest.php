<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * طلب تحديث بيانات ملف طالب.
 */
final class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return $this->user()->can('update', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_language' => ['sometimes', 'nullable', 'string', 'in:ar,en,fr'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before' => __('students::validation.birth_before_today'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'date_of_birth',
            'gender',
            'nationality',
            'country',
            'city',
            'preferred_language',
            'notes',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('students::attributes.'.$field)],
        )->all();
    }
}
