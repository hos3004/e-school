<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;

final class UpdateGuardianLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GuardianLink|null $link */
        $link = $this->route('guardian_link');

        return $link !== null && $this->user()->can('update', $link);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $allowedSections */
        $allowedSections = config('guardians.links.allowed_visible_sections', []);

        return [
            'relationship' => ['sometimes', new Enum(GuardianRelationship::class)],
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
            'relationship.Illuminate\Validation\Rules\Enum' => __('guardians::validation.relationship_invalid'),
            'visible_sections.*.Illuminate\Validation\Rules\In' => __('guardians::validation.visible_section_invalid'),
        ];
    }
}
