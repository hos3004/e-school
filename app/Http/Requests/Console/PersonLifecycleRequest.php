<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * سبب مكتوب إلزامي لكل إجراء على دورة حياة الحساب.
 *
 * الصلاحية تُفحَص على السجل نفسه في الـcontroller عبر Policy وليس هنا،
 * لأن القرار يتوقف على مؤسسة السجل لا على المستخدم وحده.
 */
final class PersonLifecycleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
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
