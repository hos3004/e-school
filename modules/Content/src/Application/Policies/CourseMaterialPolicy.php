<?php

declare(strict_types=1);

namespace Modules\Content\Application\Policies;

use Illuminate\Auth\Access\Response;
use Modules\Content\Domain\Models\CourseMaterial;

/**
 * سياسة المواد التعليمية — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات content.material.<action>، مع مقارنة
 * ملكية السجل (الرافع) أو صلاحية الإدارة العامة حيثما أمكن.
 */
final class CourseMaterialPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('content.material.view_any');
    }

    public function view($user, CourseMaterial $material): bool
    {
        return $user->can('content.material.view')
            || $material->uploaded_by === $user->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user->can('content.material.create');
    }

    public function update($user, CourseMaterial $material): bool
    {
        return $user->can('content.material.manage_all')
            || ($user->can('content.material.update')
                && $material->uploaded_by === $user->getAuthIdentifier());
    }

    public function delete($user, CourseMaterial $material): Response|bool
    {
        if ($material->trashed()) {
            return false;
        }

        return $user->can('content.material.manage_all')
            || ($user->can('content.material.delete')
                && $material->uploaded_by === $user->getAuthIdentifier());
    }
}
