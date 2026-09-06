<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use App\Http\Controllers\Learning\TeachingStudents;
use App\Http\Controllers\Portal\Support\PortalData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\AcademicReports\Domain\Contracts\StudentLearningReportQueries;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Contracts\StudentAssignmentResultQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** A profile-only projection composed from owner DTOs. Educational scope is selected before reading data. */
final readonly class PersonProfileData
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private SessionParticipantAdministrationQueries $participants,
        private AttendanceAdministrationQueries $attendance,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
        private BulkCreateIndividualQuranSchedulesAction $quran,
        private TeachingStudents $teaching,
        private PortalData $portal,
        private UserQueryService $users,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, string $organizationId, string $kind, string $profileId, string $audience, ?string $teachingStaffId = null): array
    {
        $timezone = (string) app(ConsoleContext::class)->forRequest($request)['timezone'];
        $requested = $request->query('profile_month');
        // Invalid read-only filters fall back to the current month, never redirect into an error loop.
        $month = is_string($requested) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)
            && (int) substr($requested, 0, 4) >= 1900 && (int) substr($requested, 0, 4) <= 9998
                ? $requested : now($timezone)->format('Y-m');
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
        $until = $from->addMonth();
        $studentId = $kind === 'student' ? $profileId : null;
        $staffId = $teachingStaffId ?? ($kind === 'teacher' ? $profileId : null);
        $lessons = [];
        $afterStart = null;
        $afterId = null;
        // The reporting service is cursor-bounded. Traverse every page, including equal start times.
        do {
            $batch = $this->sessions->forReport(
                organizationId: $organizationId, fromUtc: $from->utc(), untilUtcExclusive: $until->utc(),
                studentProfileId: $studentId, staffProfileId: $staffId,
                afterScheduledStart: $afterStart, afterId: $afterId,
            );
            foreach ($batch as $lesson) {
                $lessons[$lesson->id] = $lesson;
            }
            $last = $batch === [] ? null : $batch[array_key_last($batch)];
            $afterStart = $last === null ? null : CarbonImmutable::parse($last->scheduledStart)->utc();
            $afterId = $last?->id;
        } while ($batch !== []);
        $selected = [];
        if ($studentId !== null) {
            foreach ($this->participants->forSessions($organizationId, array_keys($lessons)) as $sessionId => $participants) {
                foreach ($participants as $participant) {
                    if ($participant->studentProfileId === $studentId && $participant->invitationActive) {
                        $selected[$sessionId] = $participant;
                    }
                }
            }
            $lessons = array_intersect_key($lessons, $selected);
        }
        $attendance = $this->attendance->byParticipantIds($organizationId, array_values(array_map(
            static fn ($participant): string => $participant->id, $selected,
        )));
        $courses = $this->academics->coursesByIds($organizationId, array_values(array_unique(array_map(
            static fn ($lesson): string => $lesson->courseId, $lessons,
        ))));
        $groups = $this->groups->groupsByIds($organizationId, array_values(array_unique(array_filter(array_map(
            static fn ($lesson): string => $lesson->groupId, $lessons,
        )))));
        $names = $this->staff->namesForProfiles($organizationId, array_values(array_unique(array_map(
            static fn ($lesson): string => $lesson->staffProfileId, $lessons,
        ))));
        $counts = ['completed' => 0, 'recorded' => 0, 'absent' => 0, 'attended' => 0];
        $rows = [];
        foreach ($lessons as $lesson) {
            $record = isset($selected[$lesson->id]) ? ($attendance[$selected[$lesson->id]->id] ?? null) : null;
            $state = $record === null ? null : AttendanceStatus::from($record->status);
            $counts['completed'] += (int) ($lesson->status === 'completed');
            $counts['recorded'] += (int) ($record !== null);
            $counts['absent'] += (int) ($state?->isViolation() ?? false);
            $counts['attended'] += (int) ($state?->isPresent() ?? false);
            $course = $courses[$lesson->courseId] ?? null;
            $group = $groups[$lesson->groupId] ?? null;
            $rows[] = [
                'id' => $lesson->id, 'title' => $this->label($lesson->title),
                'course' => $course === null ? __('console_profiles.unknown') : $this->label($course->name),
                'group' => $group === null ? null : $this->label($group->name),
                'teacher' => $names[$lesson->staffProfileId] ?? __('console_profiles.unknown'),
                'status' => $lesson->status, 'statusLabel' => __('sessions::status.'.$lesson->status),
                'start' => $lesson->scheduledStart, 'end' => $lesson->scheduledEnd,
                'attendance' => $state?->value, 'attendanceLabel' => $state?->label(),
                'attendedMinutes' => $record?->attendedMinutes,
                'url' => $audience !== 'admin' && $request->user()?->can('session.view')
                    ? route('learning.'.($audience === 'teacher' ? 'teacher' : $kind).'.sessions.show', ['session' => $lesson->id]) : null,
            ];
        }
        $individual = [];
        $students = [];
        if ($audience !== 'teacher') {
            foreach ($this->quran->activeScheduleSummariesByStudent($organizationId) as $id => $schedule) {
                if (($kind === 'student' && $id !== $profileId)
                    || ($kind === 'teacher' && $schedule['staff_profile_id'] !== $profileId)) {
                    continue;
                }
                $teacherNames = $this->staff->namesForProfiles($organizationId, [(string) $schedule['staff_profile_id']]);
                $individual[] = [
                    'id' => $schedule['id'], 'course' => __('console_profiles.individual'),
                    'teacher' => $teacherNames[$schedule['staff_profile_id']] ?? __('console_profiles.unknown'),
                    'time' => implode(' · ', array_map(static fn (array $slot): string => __('staff::admin.availability.weekdays.'.$slot['weekday']).' '.$slot['start_time'], $schedule['weekly_slots'])),
                    'timezone' => $schedule['timezone'], 'starts_on' => $schedule['starts_on'], 'ends_on' => $schedule['ends_on'],
                    'duration_minutes' => $schedule['duration_minutes'],
                ];
            }
            if ($kind === 'teacher' && $request->user()?->can($audience === 'admin' ? 'student.view.any' : 'student.view')) {
                foreach ($this->teaching->roster($organizationId, $profileId, app()->getLocale()) as $student) {
                    $students[] = [
                        'id' => $student['id'], 'name' => $student['name'], 'code' => $student['code'],
                        'url' => route($audience === 'admin' ? 'console.students.show' : 'learning.teacher.students.show',
                            $audience === 'admin' ? ['profile' => $student['id']] : ['student' => $student['id']]),
                    ];
                }
            }
        }
        $assignments = $kind === 'student'
            ? $this->studentAssignments($request, $organizationId, $profileId, $audience, $teachingStaffId)
            : [];
        $reports = [];
        if ($kind === 'student' && $audience !== 'teacher') {
            if ($request->user()?->can($audience === 'self' ? 'session_report.view' : 'report.view')) {
                $reports = array_map(static fn (array $report): array => [
                    'id' => $report['id'], 'title' => $report['title'], 'issuedAt' => $report['issuedAt'], 'summary' => $report['summary'],
                ], $this->portal->monthlyReports($profileId, app()->getLocale(), $organizationId));
            }
        }

        $learningReports = [];
        if ($kind === 'student' && $request->user()?->can('session_report.view')) {
            foreach (app(StudentLearningReportQueries::class)
                ->forStudent($organizationId, $profileId, array_keys($lessons), $teachingStaffId) as $report) {
                $learningReports[] = [
                    'id' => $report->id, 'sessionId' => $report->sessionId, 'submittedAt' => $report->submittedAt,
                    'participation' => $report->participation, 'performance' => $report->performance, 'commitment' => $report->commitment,
                    'strengths' => $report->strengths, 'weaknesses' => $report->weaknesses, 'note' => $report->note,
                    'maxScore' => SessionReportStudent::MAX_SCORE,
                ];
            }
        }

        return ['generatedAt' => now('UTC')->toIso8601String(), 'month' => $month, 'until' => $until->subDay()->toDateString(), 'sessions' => $rows, 'counts' => $counts,
            'individual' => $individual, 'students' => $students, 'assignments' => $assignments, 'reports' => $reports, 'learningReports' => $learningReports];
    }

    /** @return list<array<string,mixed>> */
    private function studentAssignments(Request $request, string $organizationId, string $studentId, string $audience, ?string $teachingStaffId): array
    {
        $actor = $request->user();
        if ($actor === null) {
            return [];
        }
        $canRead = $audience === 'self' ? $actor->can('assignment.submit')
            : ($actor->can('assignment.manage') || $actor->can('assignment.grade'));
        if (!$canRead) {
            return [];
        }
        $staffScope = $teachingStaffId;
        if ($audience === 'teacher' && $staffScope === null) {
            return [];
        }
        if ($audience === 'admin') {
            // Match AssignmentPolicy's broad administrative access; otherwise keep teacher-owned results only.
            $canReadAll = $actor->can('assignment.manage')
                && ($actor->can('settings.manage') || $actor->can('student.update') || $actor->can('message.moderate'));
            if (!$canReadAll) {
                $staffScope = app(AssignmentAudienceQueries::class)->forUser($organizationId, (string) $actor->getAuthIdentifier())->staffProfileId;
                if ($staffScope === null) {
                    return [];
                }
            }
        }
        $student = app(StudentDirectoryQueries::class)->byIds($organizationId, [$studentId])[$studentId] ?? null;
        if ($student === null) {
            return [];
        }
        $items = app(StudentAssignmentResultQueries::class)->forStudent($organizationId, $student->userId, $staffScope);
        $courses = $this->academics->coursesByIds($organizationId, array_values(array_unique(array_map(static fn ($item): string => $item->courseId, $items))));

        return array_map(fn ($item): array => [
            'id' => $item->id, 'title' => $this->label($item->title),
            'courseName' => isset($courses[$item->courseId]) ? $this->label($courses[$item->courseId]->name) : __('console_profiles.unknown'),
            'dueAt' => $item->dueAt, 'submissionStatus' => $item->submissionStatus, 'submissionStatusLabel' => $item->submissionStatusLabel,
            'submittedAt' => $item->submittedAt, 'submissionContent' => $item->submissionContent, 'gradedAt' => $item->gradedAt,
            'score' => $item->score, 'maxScore' => $item->maxScore, 'feedback' => $item->feedback,
            'url' => $audience === 'self' ? route('learning.student.dashboard').'#tasks'
                : ($audience === 'teacher' ? route('learning.teacher.assignments') : null),
        ], $items);
    }

    public function studyLabel(string $organizationId, string $kind, string $profileId): string
    {
        if ($kind === 'teachers') {
            $rows = $this->groups->assignmentsForTeacher($organizationId, $profileId);
            $today = now('UTC')->toDateString();
            $names = array_map(fn ($group): string => $this->label($group->groupName), array_filter(
                $rows, static fn ($group): bool => $group->groupStatus === 'active'
                    && ($group->assignedFrom === null || substr($group->assignedFrom, 0, 10) <= $today)
                    && ($group->assignedTo === null || substr($group->assignedTo, 0, 10) >= $today),
            ));
        } else {
            $rows = $this->groups->membershipsForStudent($organizationId, $profileId);
            $names = array_map(fn ($group): string => $this->label($group->groupName), array_filter(
                $rows, static fn ($group): bool => $group->membershipStatus === 'active' && $group->leftAt === null,
            ));
            if ($names === []) {
                $enrollments = app(EnrollmentAdministrationQueries::class)->forStudent($organizationId, $profileId);
                $programs = $this->academics->programsByIds($organizationId, array_values(array_unique(array_map(
                    static fn ($enrollment): string => $enrollment->programId, $enrollments,
                ))));
                $names = array_map(fn ($program): string => $this->label($program->name), $programs);
            }
        }

        return $names === [] ? __('console_people.no_study_link') : implode(' · ', array_unique($names));
    }

    /** @return array<string, string|null> */
    public function identity(string $organizationId, string $userId): array
    {
        $summary = $this->users->findSummary($userId);
        if ($summary === null || !hash_equals($organizationId, $summary->organizationId)) {
            return [];
        }

        $avatar = $summary->avatarPath;
        $avatarUrl = is_string($avatar) && $avatar !== '' && !str_contains($avatar, '..')
            && !str_starts_with($avatar, '/') && !preg_match('/^[a-z]+:/i', $avatar)
            ? Storage::disk('public')->url($avatar) : null;

        return ['status' => __('identity::status.'.$summary->status), 'timezone' => $summary->timezone, 'avatarUrl' => $avatarUrl, 'statusTone' => $summary->isActive() ? 'active' : 'inactive'];
    }

    /** @param array<string, string> $names */
    private function label(array $names): string
    {
        return $names['ar'] ?? __('console_profiles.unknown');
    }
}
