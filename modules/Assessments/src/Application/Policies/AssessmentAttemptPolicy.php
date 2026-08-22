<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Modules\Assessments\Domain\Models\AssessmentAttempt;

/**
 * سياسة محاولات الاختبار — الملكية عبر مؤسسة الاختبار المرتبط.
 */
final class AssessmentAttemptPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('assessments.attempt.view_any');
    }

    public function view($user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.view')
            && $attempt->assessment?->organization_id === $user->organization_id;
    }

    /** بدء محاولة جديدة — يمر إضافيًا بحراس نافذة التوفر وسقف المحاولات. */
    public function create($user): bool
    {
        return $user->can('assessments.attempt.start');
    }

    public function update($user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.update')
            && $attempt->assessment?->organization_id === $user->organization_id;
    }

    public function delete($user, AssessmentAttempt $attempt): bool
    {
        return false;
    }

    public function submit($user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.submit')
            && $attempt->assessment?->organization_id === $user->organization_id;
    }

    public function grade($user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessments.attempt.grade')
            && $attempt->assessment?->organization_id === $user->organization_id;
    }
}
