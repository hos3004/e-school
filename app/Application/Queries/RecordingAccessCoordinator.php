<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\ValueObjects\RecordingAdministrationData;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class RecordingAccessCoordinator
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private StaffQueries $staff,
        private StudentDirectoryQueries $students,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
        private ProgramRulesQueries $programs,
        private RecordingAdministrationQueries $recordings,
    ) {}

    public function canWatch(
        Authenticatable&Authorizable $user,
        RecordingAdministrationData $recording,
    ): bool {
        $organizationId = (string) data_get($user, 'organization_id');
        $userId = (string) $user->getAuthIdentifier();
        if ($organizationId === ''
            || $organizationId !== $recording->organizationId
            || $recording->status !== 'ready') {
            return false;
        }

        if ($user->can('recording.view.any')) {
            return true;
        }

        if (!$user->can('recording.view')) {
            return false;
        }

        $session = $this->sessions->findForOrganization($organizationId, $recording->sessionId);
        if ($session === null) {
            return false;
        }

        if ((bool) config('recordings.access.teacher_of_session')
            && $this->staff->userIdForProfile($organizationId, $session->staffProfileId) === $userId) {
            return true;
        }

        $student = $this->students->forUserIds($organizationId, [$userId])[0] ?? null;
        if ($student === null || $student->archived || !$this->studentHasCourseAccess(
            $organizationId,
            $student->id,
            $session->courseId,
        )) {
            return false;
        }

        $groupIds = array_values(array_map(
            static fn (mixed $membership): string => $membership->groupId,
            array_filter(
                $this->groups->membershipsForStudent($organizationId, $student->id),
                static fn (mixed $membership): bool => $membership->leftAt === null,
            ),
        ));

        return $this->recordings->hasActiveGrantFor(
            $organizationId,
            $recording->id,
            $userId,
            $groupIds,
        );
    }

    private function studentHasCourseAccess(
        string $organizationId,
        string $studentProfileId,
        string $courseId,
    ): bool {
        if (!(bool) config('recordings.access.blocked_for_frozen_enrollment', true)) {
            return true;
        }

        $programIds = $this->programs->programIdsOfCourse($courseId);

        return collect($this->enrollments->forStudent($organizationId, $studentProfileId))
            ->contains(static fn (mixed $enrollment): bool => in_array($enrollment->programId, $programIds, true)
                && EnrollmentStatus::tryFrom($enrollment->status)?->grantsCourseAccess() === true);
    }
}
