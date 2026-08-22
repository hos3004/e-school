<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Modules\Assessments\Domain\Models\Assessment;

/**
 * سياسة الاختبارات — بوابة الصلاحيات + ملكية السجل، بلا فحص أدوار.
 */
final class AssessmentPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('assessments.assessment.view_any');
    }

    public function view($user, Assessment $assessment): bool
    {
        return $user->can('assessments.assessment.view')
            && $assessment->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('assessments.assessment.create');
    }

    public function update($user, Assessment $assessment): bool
    {
        return $user->can('assessments.assessment.update')
            && $assessment->organization_id === $user->organization_id;
    }

    public function delete($user, Assessment $assessment): bool
    {
        return $user->can('assessments.assessment.delete')
            && $assessment->organization_id === $user->organization_id;
    }

    /** إضافة سؤال أو إدارة بنك أسئلة الاختبار. */
    public function manageQuestions($user, Assessment $assessment): bool
    {
        return $user->can('assessments.question.manage')
            && $assessment->organization_id === $user->organization_id;
    }
}
