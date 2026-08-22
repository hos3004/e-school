<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianProfile;

final class LinkStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GuardianProfile|null $profile */
        $profile = $this->route('guardian_profile');

        return $profile !== null && $this->user()->can('linkStudents', $profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $allowedSections */
        $allowedSections = config('guardians.links.allowed_visible_sections', []);

        return [
            'student_profile_id' => ['required', 'string', 'size:26'],
            'relationship' => ['required', new Enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'can_act_for' => ['sometimes', 'boolean'],
            'visible_sections' => ['sometimes', 'nullable', 'array'],
            'visible_sections.*' => [Rule::in($allowedSections)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_profile_id.required' => __('guardians::validation.student_profile_id_required'),
            'student_profile_id.size' => __('guardians::validation.identifier_size'),
            'relationship.required' => __('guardians::validation.relationship_required'),
            'relationship.Illuminate\Validation\Rules\Enum' => __('guardians::validation.relationship_invalid'),
            'visible_sections.*.Illuminate\Validation\Rules\In' => __('guardians::validation.visible_section_invalid'),
        ];
    }
}
