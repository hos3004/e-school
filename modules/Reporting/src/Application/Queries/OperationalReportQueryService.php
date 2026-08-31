<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportData;
use Modules\Reporting\Domain\ValueObjects\OperationalReportRow;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** يركّب تقريرًا مسطحًا من عقود batch فقط؛ لا Models ولا joins عابرة. */
final readonly class OperationalReportQueryService implements OperationalReportQuery
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private SessionParticipantAdministrationQueries $participants,
        private AttendanceAdministrationQueries $attendances,
        private StudentDirectoryQueries $students,
        private StaffQueries $staff,
        private GroupAdministrationQueries $groups,
        private AcademicCatalogQueries $academics,
        private SessionReportStatusQueries $sessionReports,
    ) {}

    public function run(OperationalReportCriteria $criteria): OperationalReportData
    {
        $maxRows = max(1, (int) config('reporting.operational.max_rows'));
        $pageSize = max(1, (int) config('reporting.operational.scan_page_size'));
        $sourcePageLimit = max(1, (int) config('sessions.reporting.max_items'));
        $pageSize = min($pageSize, $maxRows + 1, $sourcePageLimit);
        $rows = [];
        $limitExceeded = false;
        $afterScheduledStart = null;
        $afterId = null;

        while (true) {
            $sessions = $this->sessionRows($criteria, $pageSize, $afterScheduledStart, $afterId);

            if ($sessions === []) {
                break;
            }

            $context = $this->context($criteria, $sessions);

            foreach ($sessions as $session) {
                $row = $this->row($criteria, $session, $context);

                if ($criteria->attendanceStatuses !== []
                    && !$this->matchesAttendance($row, $criteria->attendanceStatuses)) {
                    continue;
                }

                if ($criteria->reportStatus !== null && $row->reportStatus !== $criteria->reportStatus) {
                    continue;
                }

                if ($criteria->search !== '' && !$this->matchesSearch($row, $criteria->search)) {
                    continue;
                }

                $rows[] = $row;

                if (count($rows) > $maxRows) {
                    $limitExceeded = true;

                    break 2;
                }
            }

            if (count($sessions) < $pageSize) {
                break;
            }

            $lastSession = $sessions[array_key_last($sessions)];
            $afterScheduledStart = CarbonImmutable::parse($lastSession->scheduledStart);
            $afterId = $lastSession->id;
        }

        if ($limitExceeded) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        return new OperationalReportData($criteria, $rows, $this->summary($rows), $limitExceeded);
    }

    public function options(OperationalReportCriteria $criteria): array
    {
        $baseCriteria = new OperationalReportCriteria(
            organizationId: $criteria->organizationId,
            fromUtc: $criteria->fromUtc,
            untilUtcExclusive: $criteria->untilUtcExclusive,
            timezone: $criteria->timezone,
            preset: $criteria->preset,
            fromDate: $criteria->fromDate,
            untilDate: $criteria->untilDate,
            staffProfileId: $criteria->forcedToOwnTeacher ? $criteria->staffProfileId : null,
            forcedToOwnTeacher: $criteria->forcedToOwnTeacher,
        );
        $sessions = $this->sessionRows($baseCriteria, max(1, (int) config('reporting.operational.max_rows')));

        if ($sessions === []) {
            return ['students' => [], 'teachers' => [], 'groups' => [], 'courses' => []];
        }

        $sessionIds = array_column($sessions, 'id');
        $participants = $this->participants->forSessions($criteria->organizationId, $sessionIds);
        $studentIds = $this->participantStudentIds($participants);
        $teacherIds = $this->stringIds(array_merge(
            array_column($sessions, 'staffProfileId'),
            array_filter(array_column($sessions, 'originalStaffProfileId')),
        ));
        $groupIds = $this->stringIds(array_column($sessions, 'groupId'));
        $courseIds = $this->stringIds(array_column($sessions, 'courseId'));

        return [
            'students' => $this->sorted($this->students->namesForProfiles($criteria->organizationId, $studentIds)),
            'teachers' => $this->sorted($this->staff->namesForProfiles($criteria->organizationId, $teacherIds)),
            'groups' => $this->sorted($this->groupLabels($criteria->organizationId, $groupIds)),
            'courses' => $this->sorted($this->courseLabels($criteria->organizationId, $courseIds)),
        ];
    }

    public function selectedOptions(OperationalReportCriteria $criteria): array
    {
        $studentIds = $this->stringIds([$criteria->studentProfileId]);
        $teacherIds = $this->stringIds([$criteria->staffProfileId, $criteria->originalStaffProfileId]);
        $groupIds = $this->stringIds([$criteria->groupId]);
        $courseIds = $this->stringIds([$criteria->courseId]);

        return [
            'students' => $studentIds === []
                ? []
                : $this->students->namesForProfiles($criteria->organizationId, $studentIds),
            'teachers' => $teacherIds === []
                ? []
                : $this->staff->namesForProfiles($criteria->organizationId, $teacherIds),
            'groups' => $groupIds === [] ? [] : $this->groupLabels($criteria->organizationId, $groupIds),
            'courses' => $courseIds === [] ? [] : $this->courseLabels($criteria->organizationId, $courseIds),
        ];
    }

    /** @return list<SessionAdministrationData> */
    private function sessionRows(
        OperationalReportCriteria $criteria,
        int $limit,
        ?CarbonImmutable $afterScheduledStart = null,
        ?string $afterId = null,
    ): array {
        return $this->sessions->forReport(
            organizationId: $criteria->organizationId,
            fromUtc: $criteria->fromUtc,
            untilUtcExclusive: $criteria->untilUtcExclusive,
            statuses: $criteria->statuses,
            studentProfileId: $criteria->studentProfileId,
            staffProfileId: $criteria->staffProfileId,
            groupId: $criteria->groupId,
            courseId: $criteria->courseId,
            sessionTypes: $criteria->sessionTypes,
            originalStaffProfileId: $criteria->originalStaffProfileId,
            limit: $limit,
            afterScheduledStart: $afterScheduledStart,
            afterId: $afterId,
        );
    }

    /**
     * @param list<SessionAdministrationData> $sessions
     * @return array<string, mixed>
     */
    private function context(OperationalReportCriteria $criteria, array $sessions): array
    {
        $sessionIds = array_column($sessions, 'id');
        $participants = $this->participants->forSessions($criteria->organizationId, $sessionIds);

        if ($criteria->studentProfileId !== null) {
            foreach ($participants as $sessionId => $sessionParticipants) {
                $participants[$sessionId] = array_values(array_filter(
                    $sessionParticipants,
                    static fn (SessionParticipantAdministrationData $participant): bool => $participant->studentProfileId === $criteria->studentProfileId,
                ));
            }
        }

        $participantIds = [];
        foreach ($participants as $sessionParticipants) {
            foreach ($sessionParticipants as $participant) {
                $participantIds[] = $participant->id;
            }
        }

        $teacherIds = $this->stringIds(array_merge(
            array_column($sessions, 'staffProfileId'),
            array_filter(array_column($sessions, 'originalStaffProfileId')),
        ));
        $groupIds = $this->stringIds(array_column($sessions, 'groupId'));
        $courseIds = $this->stringIds(array_column($sessions, 'courseId'));

        return [
            'participants' => $participants,
            'attendances' => $this->attendances->byParticipantIds($criteria->organizationId, $participantIds),
            'student_names' => $this->students->namesForProfiles(
                $criteria->organizationId,
                $this->participantStudentIds($participants),
            ),
            'teacher_names' => $this->staff->namesForProfiles($criteria->organizationId, $teacherIds),
            'group_labels' => $this->groupLabels($criteria->organizationId, $groupIds),
            'course_labels' => $this->courseLabels($criteria->organizationId, $courseIds),
            'report_statuses' => $this->sessionReports->forSessions($sessionIds),
        ];
    }

    /** @param array<string, mixed> $context */
    private function row(
        OperationalReportCriteria $criteria,
        SessionAdministrationData $session,
        array $context,
    ): OperationalReportRow {
        $locale = app()->getLocale();
        $scheduledStart = CarbonImmutable::parse($session->scheduledStart);
        $scheduledEnd = CarbonImmutable::parse($session->scheduledEnd);
        $actualStart = $session->actualStart === null ? null : CarbonImmutable::parse($session->actualStart);
        $actualEnd = $session->actualEnd === null ? null : CarbonImmutable::parse($session->actualEnd);
        $format = (string) config('reporting.operational.datetime_format');
        $status = SessionStatus::tryFrom($session->status);
        $students = [];
        $presentCount = 0;
        $absentCount = 0;

        /** @var list<SessionParticipantAdministrationData> $sessionParticipants */
        $sessionParticipants = $context['participants'][$session->id] ?? [];
        foreach ($sessionParticipants as $participant) {
            $attendance = $context['attendances'][$participant->id] ?? null;
            $attendanceStatus = is_object($attendance) ? (string) $attendance->status : 'unrecorded';
            $attendanceEnum = AttendanceStatus::tryFrom($attendanceStatus);

            if ($attendanceEnum?->isPresent() === true) {
                $presentCount++;
            } elseif (in_array($attendanceEnum, [AttendanceStatus::Absent, AttendanceStatus::NoShow, AttendanceStatus::Excused], true)) {
                $absentCount++;
            }

            $students[] = [
                'id' => $participant->studentProfileId,
                'name' => (string) ($context['student_names'][$participant->studentProfileId]
                    ?? __('reporting::operational.unknown_student')),
                'attendance_status' => $attendanceStatus,
                'attendance_label' => $attendanceEnum?->label() ?? __('reporting::operational.attendance.unrecorded'),
                'attended_minutes' => is_object($attendance)
                    ? max(0, (int) $attendance->attendedMinutes)
                    : max(0, $participant->attendedMinutes),
            ];
        }

        $originalTeacherId = $session->originalStaffProfileId ?: $session->staffProfileId;
        $report = $context['report_statuses'][$session->id] ?? null;
        $reportStatus = is_object($report) ? $report->state() : $this->missingReportState($status);

        return new OperationalReportRow(
            id: $session->id,
            title: $this->localized($session->title, __('reporting::operational.unknown_session')),
            scheduledStart: $scheduledStart->toIso8601String(),
            scheduledEnd: $scheduledEnd->toIso8601String(),
            scheduledStartDisplay: $scheduledStart->setTimezone($criteria->timezone)->translatedFormat($format),
            scheduledEndDisplay: $scheduledEnd->setTimezone($criteria->timezone)->translatedFormat($format),
            durationMinutes: max(0, (int) $scheduledStart->diffInMinutes($scheduledEnd)),
            actualDurationMinutes: $actualStart !== null && $actualEnd !== null
                ? max(0, (int) $actualStart->diffInMinutes($actualEnd))
                : null,
            courseId: $session->courseId,
            course: (string) ($context['course_labels'][$session->courseId] ?? __('reporting::operational.unknown_course')),
            groupId: $session->groupId,
            group: (string) ($context['group_labels'][$session->groupId] ?? __('reporting::operational.unknown_group')),
            actualTeacherId: $session->staffProfileId,
            actualTeacher: (string) ($context['teacher_names'][$session->staffProfileId] ?? __('reporting::operational.unknown_teacher')),
            originalTeacherId: $originalTeacherId,
            originalTeacher: (string) ($context['teacher_names'][$originalTeacherId] ?? __('reporting::operational.unknown_teacher')),
            hasSubstitute: $originalTeacherId !== $session->staffProfileId,
            students: $students,
            studentsDisplay: $students === []
                ? __('reporting::operational.no_students')
                : implode((string) __('reporting::operational.separators.list'), array_map(
                    static fn (array $student): string => $student['name'].' — '.$student['attendance_label'],
                    $students,
                )),
            attendanceSummary: $this->attendanceSummary($presentCount, $absentCount, count($students)),
            presentCount: $presentCount,
            absentCount: $absentCount,
            status: $session->status,
            statusLabel: $status?->label() ?? $session->status,
            statusColor: $status?->color() ?? 'gray',
            sessionType: $session->sessionType ?? '',
            sessionTypeLabel: $session->sessionType === null
                ? __('reporting::operational.not_available')
                : __('sessions::session_types.'.$session->sessionType),
            cancellationReason: $session->cancellationReason,
            reportStatus: $reportStatus,
            reportStatusLabel: __('reporting::operational.report_status.'.$reportStatus),
        );
    }

    /**
     * @param list<OperationalReportRow> $rows
     * @return array<string, int|float|string>
     */
    private function summary(array $rows): array
    {
        $statusCounts = array_count_values(array_column($rows, 'status'));
        $cancelled = array_sum(array_intersect_key($statusCounts, array_flip([
            SessionStatus::CancelledByStudent->value,
            SessionStatus::CancelledByTeacher->value,
            SessionStatus::CancelledBySchool->value,
        ])));
        $present = array_sum(array_column($rows, 'presentCount'));
        $absent = array_sum(array_column($rows, 'absentCount'));
        $attendanceDecisions = $present + $absent;

        return [
            'total' => count($rows),
            'completed' => $statusCounts[SessionStatus::Completed->value] ?? 0,
            'cancelled' => $cancelled,
            'postponed' => $statusCounts[SessionStatus::Postponed->value] ?? 0,
            'no_show' => $statusCounts[SessionStatus::NoShow->value] ?? 0,
            'excused' => $statusCounts[SessionStatus::Excused->value] ?? 0,
            'scheduled' => array_sum(array_intersect_key($statusCounts, array_flip([
                SessionStatus::Draft->value, SessionStatus::Scheduled->value, SessionStatus::Confirmed->value,
                SessionStatus::InProgress->value, SessionStatus::AwaitingReview->value,
            ]))),
            'students' => count(array_unique(array_merge(...array_map(
                static fn (OperationalReportRow $row): array => array_column($row->students, 'id'),
                $rows,
            )))),
            'teachers' => count(array_unique(array_column($rows, 'actualTeacherId'))),
            'groups' => count(array_unique(array_column($rows, 'groupId'))),
            'present' => $present,
            'absent' => $absent,
            'attendance_rate' => $attendanceDecisions === 0 ? 0.0 : round(($present / $attendanceDecisions) * 100, 1),
            'scheduled_minutes' => array_sum(array_column($rows, 'durationMinutes')),
            'actual_minutes' => array_sum(array_filter(array_column($rows, 'actualDurationMinutes'), 'is_int')),
            'reports_submitted' => count(array_filter($rows, static fn (OperationalReportRow $row): bool => $row->reportStatus === 'submitted')),
            'reports_late' => count(array_filter($rows, static fn (OperationalReportRow $row): bool => $row->reportStatus === 'late')),
            'reports_missing' => count(array_filter($rows, static fn (OperationalReportRow $row): bool => $row->reportStatus === 'missing')),
        ];
    }

    private function missingReportState(?SessionStatus $status): string
    {
        return in_array($status, [SessionStatus::AwaitingReview, SessionStatus::Completed, SessionStatus::NoShow, SessionStatus::Excused], true)
            ? 'missing'
            : 'not_required';
    }

    private function attendanceSummary(int $present, int $absent, int $total): string
    {
        if ($total === 0) {
            return __('reporting::operational.attendance.unrecorded');
        }

        return implode(' · ', [
            __('reporting::operational.attendance.present_count', ['count' => $present]),
            __('reporting::operational.attendance.absent_count', ['count' => $absent]),
        ]);
    }

    /** @param list<string> $statuses */
    private function matchesAttendance(OperationalReportRow $row, array $statuses): bool
    {
        foreach ($row->students as $student) {
            if (in_array($student['attendance_status'], $statuses, true)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSearch(OperationalReportRow $row, string $search): bool
    {
        $needle = mb_strtolower($search);
        $haystack = mb_strtolower(implode(' ', [
            $row->title, $row->course, $row->group, $row->actualTeacher,
            $row->originalTeacher, $row->studentsDisplay, $row->statusLabel,
        ]));

        return str_contains($haystack, $needle);
    }

    /**
     * @param array<string, list<SessionParticipantAdministrationData>> $participants
     * @return list<string>
     */
    private function participantStudentIds(array $participants): array
    {
        $ids = [];
        foreach ($participants as $sessionParticipants) {
            foreach ($sessionParticipants as $participant) {
                $ids[] = $participant->studentProfileId;
            }
        }

        return $this->stringIds($ids);
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private function stringIds(array $values): array
    {
        return array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function groupLabels(string $organizationId, array $ids): array
    {
        $labels = [];
        foreach ($this->groups->groupsByIds($organizationId, $ids) as $id => $group) {
            $labels[$id] = trim($group->code.' — '.$this->localized($group->name, ''), ' —');
        }

        return $labels;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function courseLabels(string $organizationId, array $ids): array
    {
        $labels = [];
        foreach ($this->academics->coursesByIds($organizationId, $ids) as $id => $course) {
            $labels[$id] = trim($course->code.' — '.$this->localized($course->name, ''), ' —');
        }

        return $labels;
    }

    /** @param array<string, string> $translations */
    private function localized(array $translations, string $fallback): string
    {
        $locale = app()->getLocale();
        $value = $translations[$locale] ?? $translations['ar'] ?? $translations['en'] ?? reset($translations);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    private function sorted(array $options): array
    {
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}
