<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Assessments\Domain\Models\AssessmentAttempt;

/**
 * سياسة محاولات الاختبار — الملكية عبر مؤسسة الاختبار المرتبط.
 */
final class AssessmentAttemptPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessments.attempt.view_any');
    }

    public function view(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.view')
            && $attempt->assessment?->organization_id === data_get($user, 'organization_id');
    }

    /** بدء محاولة جديدة — يمر إضافيًا بحراس نافذة التوفر وسقف المحاولات. */
    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessments.attempt.start');
    }

    public function update(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.update')
            && $attempt->assessment?->organization_id === data_get($user, 'organization_id');
    }

    public function delete(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return false;
    }

    public function submit(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.submit')
            && $attempt->assessment?->organization_id === data_get($user, 'organization_id');
    }

    public function grade(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.grade')
            && $attempt->assessment?->organization_id === data_get($user, 'organization_id');
    }
}
