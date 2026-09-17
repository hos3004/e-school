<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Guardians\Domain\Enums\GuardianRelationship;

/**
 * ربط طالب بحساب ولي أمر قائم من صفحة ملف الوصي.
 */
final class GuardianLinkStoreRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'ulid'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['nullable', 'boolean'],
            'can_act_for' => ['nullable', 'boolean'],
            'visible_sections' => ['nullable', 'array'],
            'visible_sections.*' => ['string', Rule::in((array) config('guardians.links.allowed_visible_sections'))],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    public function organizationId(): string
    {
        $id = $this->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    public function actorId(): string
    {
        return (string) $this->user()?->getAuthIdentifier();
    }
}
