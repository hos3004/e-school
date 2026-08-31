<?php

declare(strict_types=1);

namespace Modules\Content\Application\Policies;

use Modules\Content\Domain\Models\CourseMaterial;

/**
 * سياسة المواد التعليمية — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات content.material.<action>، مع مقارنة
 * ملكية السجل (الرافع) أو صلاحية الإدارة العامة حيثما أمكن.
 */
final class CourseMaterialPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('content.view');
    }

    public function view(mixed $user, CourseMaterial $material): bool
    {
        return $user->can('content.view') && $this->sameOrganization($user, $material);
    }

    public function create(mixed $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(mixed $user, CourseMaterial $material): bool
    {
        return $user->can('content.manage') && $this->sameOrganization($user, $material);
    }

    public function delete(mixed $user, CourseMaterial $material): bool
    {
        return !$material->trashed()
            && $user->can('content.manage')
            && $this->sameOrganization($user, $material);
    }

    public function publish(mixed $user, CourseMaterial $material): bool
    {
        return $user->can('content.manage') && $this->sameOrganization($user, $material);
    }

    private function sameOrganization(mixed $user, CourseMaterial $material): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && $organizationId !== ''
            && hash_equals($organizationId, (string) $material->organization_id);
    }
}
