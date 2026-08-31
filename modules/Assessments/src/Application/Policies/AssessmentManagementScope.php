<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;

/** Resolves the `assessment.manage` assigned scope without inspecting roles. */
final readonly class AssessmentManagementScope
{
    public function __construct(
        private StaffQueries $staff,
        private GroupAdministrationQueries $groups,
    ) {}

    public function allows(Authenticatable&Authorizable $user, Assessment $assessment): bool
    {
        if (!$user->can('assessment.manage')
            || $assessment->organization_id !== data_get($user, 'organization_id')) {
            return false;
        }

        // Curriculum managers own the tenant-wide academic scope. Teachers do
        // not receive this capability in the permission matrix.
        if ($user->can('program.manage')) {
            return true;
        }

        $courseId = $assessment->course_id === null ? null : (string) $assessment->course_id;
        $profile = $this->staff->findActiveProfileForUser((string) $user->getAuthIdentifier());

        if ($courseId === null || $profile === null) {
            return false;
        }

        $today = now('UTC')->toDateString();

        foreach ($this->groups->assignmentsForTeacher(
            (string) $assessment->organization_id,
            $profile['id'],
        ) as $assignment) {
            if ($assignment->courseId !== $courseId
                || $assignment->groupStatus !== 'active'
                || ($assignment->assignedFrom !== null && $assignment->assignedFrom > $today)
                || ($assignment->assignedTo !== null && $assignment->assignedTo < $today)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
