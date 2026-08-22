<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Groups\Application\Actions\EnrollStudentAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;

final class AssignStudentToGroupAction
{
    public function __construct(
        private readonly StudentAdmissionQueries $studentQueries,
        private readonly EnrollStudentAction $enrollStudentAction,
        private readonly ?ProgramRulesQueries $programRulesQueries = null,
    ) {}

    public function execute(
        string $studentProfileId,
        string $groupId,
        ?string $overrideReason = null,
    ): void {
        // 1. Must be cleared for assignment by Students module
        if (!$this->studentQueries->isClearedForAssignment($studentProfileId)) {
            throw new \InvalidArgumentException(__('enrollments::errors.student_not_cleared'));
        }

        /** @var Group|null $group */
        $group = Group::query()->find($groupId);
        if ($group === null) {
            throw new \InvalidArgumentException(__('groups::errors.group_not_found'));
        }

        // 2. Capacity check
        $maxCapacity = (int) config('academic.groups.max_capacity', 15);
        if ($group->capacity > $maxCapacity) {
            throw new \InvalidArgumentException(__('enrollments::errors.capacity_exceeded'));
        }

        // 3. Individual session mode check
        if ($this->programRulesQueries !== null && $group->course_id !== null) {
            $sessionMode = $this->programRulesQueries->sessionModeOfCourse((string) $group->course_id);

            if ($sessionMode === 'individual') {
                $currentMembers = DB::table('group_memberships')
                    ->where('group_id', $groupId)
                    ->whereNull('left_at')
                    ->count();

                if ($currentMembers > 0) {
                    throw new \InvalidArgumentException(__('enrollments::errors.individual_session_group_limit'));
                }
            }
        }

        // 4. Enroll student into group
        $this->enrollStudentAction->execute($group, $studentProfileId);
    }
}
