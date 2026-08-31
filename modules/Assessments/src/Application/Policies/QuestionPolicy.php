<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Assessments\Domain\Models\Question;

/**
 * سياسة الأسئلة — الملكية تُستنبط من اختبار السؤال (نفس المؤسسة).
 */
final class QuestionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessment.manage') || $user->can('assessment.take') || $user->can('grade.view');
    }

    public function view(Authenticatable&Authorizable $user, Question $question): bool
    {
        return ($user->can('assessment.manage') || $user->can('assessment.take') || $user->can('grade.view'))
            && $question->assessment?->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessment.manage');
    }

    public function update(Authenticatable&Authorizable $user, Question $question): bool
    {
        return $user->can('assessment.manage')
            && $question->assessment?->organization_id === data_get($user, 'organization_id');
    }

    public function delete(Authenticatable&Authorizable $user, Question $question): bool
    {
        return $user->can('assessment.manage')
            && $question->assessment?->organization_id === data_get($user, 'organization_id');
    }
}
