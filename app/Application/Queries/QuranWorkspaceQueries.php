<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\AcademicReports\Application\Queries\QuranProgressQueryService;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Enrollments\Domain\Contracts\EnrollmentFollowupQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;

/** Read-only composition for Quran tabs; all module data arrives through public queries. */
final readonly class QuranWorkspaceQueries
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private SessionParticipantAdministrationQueries $participants,
        private AttendanceAdministrationQueries $attendance,
        private EnrollmentFollowupQueries $enrollments,
        private StaffAdministrationQueries $staff,
        private QuranProgressQueryService $progress,
    ) {}

    /** @return list<array<string, mixed>> */
    public function enrollments(string $organizationId, string $programId): array
    {
        return array_values(array_map(static fn ($item): array => [
            'id' => $item->id, 'student_id' => $item->studentProfileId,
            'status' => $item->status, 'status_label' => EnrollmentStatus::tryFrom($item->status)?->label(),
            'held' => in_array($item->status, [EnrollmentStatus::Paused->value, EnrollmentStatus::Frozen->value, EnrollmentStatus::ReactivationRequested->value, EnrollmentStatus::UnderAssessment->value], true),
            'return_date' => $item->expectedReturnDate,
        ], array_filter($this->enrollments->forOrganization($organizationId), static fn ($item): bool => $item->programId === $programId)));
    }

    /** @param list<string> $teacherIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function availability(string $organizationId, array $teacherIds): array
    {
        $result = [];
        foreach ($teacherIds as $id) {
            $result[$id] = array_map(static fn ($slot): array => [
                'id' => $slot->id, 'weekday' => $slot->weekday, 'start_time' => substr($slot->startTime, 0, 5),
                'end_time' => substr($slot->endTime, 0, 5), 'timezone' => $slot->timezone,
                'approval_status' => $slot->approvalStatus, 'effective_from' => $slot->effectiveFrom, 'effective_to' => $slot->effectiveTo,
            ], $this->staff->availabilityForTeacher($organizationId, $id));
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function activity(string $organizationId, string $courseId, CarbonImmutable $from, CarbonImmutable $until, string $timezone, bool $canAttendance, bool $canProgress): array
    {
        $all = [];
        $afterStart = null;
        $afterId = null;
        do {
            $batch = $this->sessions->forReport($organizationId, $from, $until, courseId: $courseId, sessionTypes: ['individual'], afterScheduledStart: $afterStart, afterId: $afterId);
            if ($batch === []) {
                break;
            }
            foreach ($batch as $session) {
                $all[$session->id] = $session;
            }
            $last = $batch[array_key_last($batch)];
            $afterStart = CarbonImmutable::parse($last->scheduledStart)->utc();
            $afterId = $last->id;
        } while (true);
        $participants = $this->participants->forSessions($organizationId, array_keys($all));
        $participantIds = [];
        foreach ($participants as $items) {
            foreach ($items as $item) {
                $participantIds[] = $item->id;
            }
        }
        $attendance = $canAttendance ? $this->attendance->byParticipantIds($organizationId, $participantIds) : [];
        $reports = $canProgress ? $this->progress->forSessions($organizationId, array_keys($all)) : [];
        $students = [];
        $rows = [];
        $now = CarbonImmutable::now('UTC');
        foreach ($all as $session) {
            $start = CarbonImmutable::parse($session->scheduledStart);
            $end = CarbonImmutable::parse($session->scheduledEnd);
            foreach ($participants[$session->id] ?? [] as $participant) {
                $id = $participant->studentProfileId;
                $students[$id] ??= ['attended' => 0, 'recorded' => 0, 'absences' => 0, 'next_session' => null, 'progress' => null, 'sessions' => 0];
                $item = $attendance[$participant->id] ?? null;
                $status = $item === null ? null : AttendanceStatus::tryFrom($item->status);
                if ($status !== null) {
                    $students[$id]['recorded']++;
                    $students[$id]['attended'] += $status->isPresent() ? 1 : 0;
                    $students[$id]['absences'] += $status->isViolation() ? 1 : 0;
                }
                $students[$id]['sessions'] += $session->status === SessionStatus::Superseded->value ? 0 : 1;
                if ($start->greaterThanOrEqualTo($now) && in_array($session->status, [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value], true) && ($students[$id]['next_session'] === null || $start->toIso8601String() < $students[$id]['next_session']['utc'])) {
                    $students[$id]['next_session'] = ['utc' => $start->toIso8601String(), 'local' => $start->setTimezone($timezone)->format('Y-m-d H:i'), 'teacher_id' => $session->staffProfileId];
                }
                $report = $reports[$session->id] ?? null;
                if ($report !== null && ($students[$id]['progress'] === null || $start->toIso8601String() > $students[$id]['progress']['at'])) {
                    $students[$id]['progress'] = ['at' => $start->toIso8601String(), 'topics' => $report['topics'], 'next_plan' => $report['next_plan'], ...($report['students'][$id] ?? [])];
                }
                $rows[] = [
                    'id' => $session->id, 'student_id' => $id, 'teacher_id' => $session->staffProfileId,
                    'original_teacher_id' => $session->originalStaffProfileId, 'status' => $session->status,
                    'status_label' => SessionStatus::tryFrom($session->status)?->label(),
                    'start' => $start->setTimezone($timezone)->format('Y-m-d H:i'), 'end' => $end->setTimezone($timezone)->format('H:i'),
                    'duration' => (int) $start->diffInMinutes($end), 'attendance' => $status?->label(),
                    'report_submitted' => $report !== null, 'timezone' => $timezone,
                ];
            }
        }

        return ['students' => $students, 'sessions' => $rows];
    }
}
