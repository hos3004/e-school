<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class PostponementRecipientResolver
{
    public function __construct(
        private SessionSchedulingQueries $sessions,
        private StaffQueries $staff,
        private StudentDirectoryQueries $students,
    ) {}

    /**
     * @return array{student_user_ids: list<string>, teacher_user_id: ?string}
     */
    public function forSession(
        string $organizationId,
        string $sessionId,
        ?string $onlyStudentProfileId = null,
    ): array {
        $session = $this->sessions->find($organizationId, $sessionId);
        if ($session === null) {
            return ['student_user_ids' => [], 'teacher_user_id' => null];
        }

        $studentProfileIds = $onlyStudentProfileId === null
            ? $session->studentProfileIds
            : [$onlyStudentProfileId];
        $profiles = $this->students->byIds($organizationId, $studentProfileIds);
        $studentUserIds = [];

        foreach ($profiles as $profile) {
            if ($profile->userId !== '') {
                $studentUserIds[] = $profile->userId;
            }
        }

        return [
            'student_user_ids' => array_values(array_unique($studentUserIds)),
            'teacher_user_id' => $this->staff->userIdForProfile(
                $organizationId,
                $session->staffProfileId,
            ),
        ];
    }
}
