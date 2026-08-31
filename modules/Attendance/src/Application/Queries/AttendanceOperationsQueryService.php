<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** يثري قيود الحضور ببيانات تشغيلية عبر عقود الموديولات المالكة. */
final class AttendanceOperationsQueryService
{
    /** @var array<string, array<string, mixed>> */
    private array $contexts = [];

    public function __construct(
        private readonly SessionParticipantAdministrationQueries $participants,
        private readonly AcademicCatalogQueries $academics,
        private readonly GroupAdministrationQueries $groups,
        private readonly StaffQueries $staff,
        private readonly StudentDirectoryQueries $students,
        private readonly UserAccountDirectory $accounts,
        private readonly AuditQueryService $audit,
    ) {}

    /** @return list<string> */
    public function participantIdsForOrganization(string $organizationId): array
    {
        return $this->participants->participantIdsForOrganization($organizationId);
    }

    /** @return array<string, mixed> */
    public function participantContext(string $organizationId, string $participantId): array
    {
        $key = $organizationId.':'.$participantId;
        if (isset($this->contexts[$key])) {
            return $this->contexts[$key];
        }

        $participant = $this->participants->findForOrganization($organizationId, $participantId);
        if ($participant === null) {
            return $this->contexts[$key] = [];
        }

        return $this->contexts[$key] = $this->context($organizationId, $participant);
    }

    /** @return list<array<string, mixed>> */
    public function auditHistory(string $organizationId, string $attendanceId): array
    {
        $paginator = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => 'attendances',
            'auditable_id' => $attendanceId,
        ], (int) config('attendance.admin_hub.max_history', 25));
        $rows = [];
        foreach ($paginator->items() as $entry) {
            $actor = $entry->actorId === null ? null : $this->accounts->find($organizationId, $entry->actorId);
            $rows[] = [
                'id' => $entry->id,
                'action' => $entry->action,
                'reason' => $entry->reason,
                'actor' => $actor === null
                    ? ($entry->actorId ?? __('attendance::messages.system_actor'))
                    : $actor->name,
                'created_at' => $entry->createdAt,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function context(string $organizationId, SessionParticipantAdministrationData $participant): array
    {
        $student = $this->students->find($organizationId, $participant->studentProfileId);
        $account = $student === null ? null : $this->accounts->find($organizationId, $student->userId);
        $course = $this->academics->coursesByIds($organizationId, [$participant->courseId])[$participant->courseId] ?? null;
        $group = $participant->groupId === null
            ? null
            : ($this->groups->groupsByIds($organizationId, [$participant->groupId])[$participant->groupId] ?? null);
        $teacher = $this->staff->namesForProfiles($organizationId, [$participant->staffProfileId])[$participant->staffProfileId] ?? null;

        return [
            'participant_id' => $participant->id,
            'session_id' => $participant->sessionId,
            'session' => $this->localized($participant->sessionTitle).' · '.$participant->scheduledStart,
            // الاسم وحده — الكود يُعرض في عموده الخاص وليس ملتصقًا بالاسم.
            'student' => $account === null
                ? ($student === null ? $participant->studentProfileId : $student->studentCode)
                : $account->name,
            'student_code' => $student === null ? '' : $student->studentCode,
            'course' => $course === null
                ? $participant->courseId
                : $this->localized($course->name).' · '.$course->code,
            'group' => $group === null
                ? __('attendance::messages.not_available')
                : $this->localized($group->name).' · '.$group->code,
            'teacher' => $teacher ?? $participant->staffProfileId,
            'session_status' => __('sessions::status.'.$participant->sessionStatus),
            'scheduled_start' => $participant->scheduledStart,
            'scheduled_end' => $participant->scheduledEnd,
            'first_joined_at' => $participant->firstJoinedAt,
            'last_left_at' => $participant->lastLeftAt,
            'classroom_minutes' => $participant->attendedMinutes,
            'invitation_active' => $participant->invitationActive,
        ];
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) (reset($value) ?: '');
    }
}
