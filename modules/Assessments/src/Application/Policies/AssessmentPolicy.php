<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Assessments\Domain\Models\Assessment;

/**
 * سياسة الاختبارات — بوابة الصلاحيات + ملكية السجل، بلا فحص أدوار.
 */
final class AssessmentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessment.manage') || $user->can('assessment.take') || $user->can('grade.view');
    }

    public function view(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        return ($user->can('assessment.manage') || $user->can('assessment.take') || $user->can('grade.view'))
            && $assessment->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessment.manage');
    }

    public function update(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        return $user->can('assessment.manage')
            && $assessment->organization_id === data_get($user, 'organization_id');
    }

    public function delete(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        return $user->can('assessment.manage')
            && $assessment->organization_id === data_get($user, 'organization_id');
    }

    /** إضافة سؤال أو إدارة بنك أسئلة الاختبار. */
    public function manageQuestions(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        return $user->can('assessment.manage')
            && $assessment->organization_id === data_get($user, 'organization_id');
    }
}
