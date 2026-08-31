<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * سياسة محاولات الاختبار — الملكية عبر مؤسسة الاختبار المرتبط.
 */
final readonly class AssessmentAttemptPolicy
{
    public function __construct(
        private StudentDirectoryQueries $students,
        private AssessmentManagementScope $management,
    ) {}

    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('assessment.manage') || $user->can('grade.view');
    }

    public function view(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        if ($attempt->assessment?->organization_id !== data_get($user, 'organization_id')) {
            return false;
        }

        if ($user->can('assessment.manage') || $user->can('grade.view')) {
            return true;
        }

        return $user->can('assessment.take') && $this->belongsToUser($user, $attempt);
    }

    /** بدء محاولة جديدة — يمر إضافيًا بحراس نافذة التوفر وسقف المحاولات. */
    public function create(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        if (!$user->can('assessment.take')
            || $assessment->organization_id !== data_get($user, 'organization_id')) {
            return false;
        }

        return $this->students->forUserIds(
            (string) $assessment->organization_id,
            [(string) $user->getAuthIdentifier()],
        ) !== [];
    }

    public function update(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return false;
    }

    public function submit(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $user->can('assessment.take')
            && $attempt->assessment?->organization_id === data_get($user, 'organization_id')
            && $this->belongsToUser($user, $attempt);
    }

    public function grade(Authenticatable&Authorizable $user, AssessmentAttempt $attempt): bool
    {
        return $attempt->assessment !== null
            && $this->management->allows($user, $attempt->assessment);
    }

    private function belongsToUser(Authenticatable $user, AssessmentAttempt $attempt): bool
    {
        $organizationId = (string) data_get($user, 'organization_id');
        $userId = (string) $user->getAuthIdentifier();

        foreach ($this->students->forUserIds($organizationId, [$userId]) as $student) {
            if ($student->id === (string) $attempt->student_profile_id && !$student->archived) {
                return true;
            }
        }

        return false;
    }
}
