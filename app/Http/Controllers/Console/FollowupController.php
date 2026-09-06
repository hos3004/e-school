<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\FollowupActionRequest;
use App\Http\Requests\Console\FollowupAttendanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assessments\Domain\Contracts\ReactivationAssessmentQueries;
use Modules\Assessments\Domain\ValueObjects\ReactivationAssessmentData;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Discipline\Application\Actions\DecideReactivationAction;
use Modules\Discipline\Application\Actions\RequestReactivationAction as CreateReactivationRequest;
use Modules\Discipline\Application\Actions\WaiveViolationAction;
use Modules\Discipline\Domain\Contracts\DisciplineFollowupQueries;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;
use Modules\Enrollments\Application\Actions\FreezeEnrollmentAction;
use Modules\Enrollments\Application\Actions\PauseEnrollmentAction;
use Modules\Enrollments\Application\Actions\ReactivateEnrollmentAction;
use Modules\Enrollments\Application\Actions\RequestReactivationAction;
use Modules\Enrollments\Application\Actions\TransitionEnrollmentStatusAction;
use Modules\Enrollments\Domain\Contracts\EnrollmentFollowupQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;

final class FollowupController extends Controller
{
    public function __construct(
        private readonly EnrollmentFollowupQueries $enrollments,
        private readonly DisciplineFollowupQueries $discipline,
        private readonly StudentDirectoryQueries $students,
        private readonly UserAccountDirectory $accounts,
        private readonly AcademicCatalogQueries $catalog,
        private readonly SessionAdministrationQueries $sessions,
        private readonly SessionParticipantAdministrationQueries $participants,
        private readonly AttendanceAdministrationQueries $attendance,
        private readonly ReactivationAssessmentQueries $assessments,
        private readonly ConsoleContext $context,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $input = $request->validate([
                'search' => ['nullable', 'string', 'max:120'], 'filter' => ['nullable', 'in:all,absence,held,directory'],
                'track' => ['nullable', 'in:all,quran,courses'], 'enrollment' => ['nullable', 'ulid'], 'page' => ['nullable', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $error) {
            throw $error->redirectTo(route('console.followup'));
        }
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        $timezone = (string) $this->context->forRequest($request)['timezone'];
        $range = DisciplineWindow::rangeEndingAt(CarbonImmutable::now('UTC'));
        $enrollments = $this->enrollments->forOrganization($organizationId);
        $ids = array_map(static fn ($row): string => $row->id, $enrollments);
        $students = $this->students->byIds($organizationId, array_values(array_unique(array_map(static fn ($row): string => $row->studentProfileId, $enrollments))));
        $accounts = $this->accounts->findMany($organizationId, array_values(array_unique(array_map(static fn ($row): string => $row->userId, $students))));
        $programs = $this->catalog->programsByIds($organizationId, array_values(array_unique(array_map(static fn ($row): string => $row->programId, $enrollments))));
        $tracks = [];
        foreach ($programs as $program) {
            $courses = $this->catalog->courses($organizationId, $program->id);
            foreach ($courses as $course) {
                $track = $course->code === config('scheduling.individual_quran.course_code') && in_array($course->sessionMode, ['individual', 'both'], true) ? 'quran' : 'courses';
                $tracks[$program->id][$track] = $track;
            }
        }
        $historyByEnrollment = [];
        $until = min($range['end'], CarbonImmutable::now('UTC'));
        $afterScheduledStart = null;
        $afterId = null;
        do {
            $sessionRows = $this->sessions->forReport(
                $organizationId,
                $range['start'],
                $until,
                afterScheduledStart: $afterScheduledStart,
                afterId: $afterId,
            );
            if ($sessionRows === []) {
                break;
            }
            $participants = $this->participants->forSessions($organizationId, array_map(static fn ($row): string => $row->id, $sessionRows));
            $participantRows = array_merge([], ...array_values($participants));
            $attendance = $this->attendance->byParticipantIds($organizationId, array_map(static fn ($row): string => $row->id, $participantRows));
            foreach ($participantRows as $participant) {
                $record = $attendance[$participant->id] ?? null;
                if ($record === null) {
                    continue;
                }
                $historyByEnrollment[$participant->enrollmentId][] = [
                    'id' => $record->id, 'session_id' => $participant->sessionId, 'title' => $participant->sessionTitle['ar'] ?? '',
                    'at' => $participant->scheduledStart, 'status' => $record->status, 'label' => AttendanceStatus::from($record->status)->label(),
                    'confirmed' => $record->confirmedAt !== null, 'actor_id' => $record->confirmedBy,
                    'reason' => $record->overrideReason, 'excused_at' => $participant->excusedAt,
                ];
            }
            $lastSession = $sessionRows[array_key_last($sessionRows)];
            $afterScheduledStart = CarbonImmutable::parse($lastSession->scheduledStart)->utc();
            $afterId = $lastSession->id;
        } while (true);
        $discipline = $this->discipline->forEnrollments($organizationId, $ids, $range['start'], $range['end']);
        $rows = [];
        foreach ($enrollments as $enrollment) {
            $student = $students[$enrollment->studentProfileId] ?? null;
            $account = $student === null ? null : ($accounts[$student->userId] ?? null);
            if ($student === null || $account === null) {
                continue;
            }
            $data = $discipline[$enrollment->id] ?? null;
            $attendanceRows = $historyByEnrollment[$enrollment->id] ?? [];
            usort($attendanceRows, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
            $absences = count(array_filter($attendanceRows, static fn (array $row): bool => in_array($row['status'], [AttendanceStatus::Absent->value, AttendanceStatus::NoShow->value], true)));
            $violations = count(array_filter($data->violations ?? [], static fn (array $row): bool => (bool) $row['countable']));
            $held = in_array($enrollment->status, ['paused', 'frozen', 'reactivation_requested', 'under_assessment'], true);
            $open = array_values(array_filter($data->requests ?? [], static fn (array $row): bool => (bool) $row['open']));
            $next = match ($enrollment->status) {
                'paused' => 'return_date', 'frozen' => 'request_return', 'reactivation_requested' => 'assessment',
                'under_assessment' => 'decision', default => $absences > 0 || $violations > 0 ? 'review_absence' : 'no_action',
            };
            $rows[] = [
                'id' => $enrollment->id, 'student_id' => $student->id, 'name' => $account->name, 'code' => $student->studentCode,
                'program' => $programs[$enrollment->programId]->name['ar'] ?? __('console_followup.unavailable'),
                'tracks' => array_values($tracks[$enrollment->programId] ?? []), 'status' => $enrollment->status,
                'status_label' => EnrollmentStatus::from($enrollment->status)->label(), 'return_date' => $enrollment->expectedReturnDate,
                'absences' => $absences, 'violations' => $violations, 'held' => $held, 'needs_action' => $held || $absences > 0 || $violations > 0 || $open !== [],
                'next' => __('console_followup.next.'.$next), 'frozen_reason' => $enrollment->frozenReason,
                'attendance' => $attendanceRows, 'discipline' => $data, 'open_request' => $open[0] ?? null,
            ];
        }
        $selectedId = $input['enrollment'] ?? null;
        $selected = $selectedId === null ? null : collect($rows)->firstWhere('id', $selectedId);
        abort_if($selectedId !== null && $selected === null, 404);
        if ($selected !== null) {
            $selected['history'] = $this->enrollments->history($organizationId, $selected['id']);
            $actorIds = [];
            foreach ([$selected['history'], $selected['attendance'], $selected['discipline']->actions ?? [], $selected['discipline']->violations ?? [], $selected['discipline']->requests ?? []] as $entries) {
                foreach ($entries as $entry) {
                    if (!empty($entry['actor_id'])) {
                        $actorIds[] = (string) $entry['actor_id'];
                    }
                }
            }
            $actors = $this->accounts->findMany($organizationId, array_values(array_unique($actorIds)));
            $selected['actors'] = array_map(static fn ($account): string => $account->name, $actors);
            $selected['assessment_options'] = $selected['open_request'] === null ? [] : array_map(
                fn (ReactivationAssessmentData $row): array => ['id' => $row->id, 'title' => $row->title['ar'] ?? __('console_followup.assessment'), 'score' => $row->score, 'total' => $row->totalScore, 'passed' => $this->assessmentPassed($row)],
                $this->assessments->forRequest($organizationId, $selected['student_id'], $selected['open_request']['id']),
            );
            $record = Enrollment::query()->forOrganization($organizationId)->findOrFail($selected['id']);
            $selected['actions'] = $this->availableActions($record, $selected['open_request'] !== null);
            $selected['submit_url'] = route('console.followup.update', ['enrollment' => $record->id]);
            $selected['can_correct_attendance'] = (bool) $request->user()?->can('attendance.override');
            $selected['can_waive'] = (bool) $request->user()?->can('discipline.waive_violations');
            $selected['attendance_statuses'] = array_values(array_map(static fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()], array_filter(AttendanceStatus::cases(), static fn (AttendanceStatus $status): bool => !$status->isViolation())));

        }
        $queue = array_values(array_filter($rows, static fn (array $row): bool => $row['needs_action']));
        $filters = ['search' => trim($input['search'] ?? ''), 'filter' => $input['filter'] ?? 'all', 'track' => $input['track'] ?? 'all'];
        $visible = array_values(array_filter($rows, static fn (array $row): bool => ($filters['filter'] === 'directory' || $row['needs_action'])
            && ($filters['filter'] !== 'held' || $row['held'])
            && ($filters['filter'] !== 'absence' || $row['absences'] > 0 || $row['violations'] > 0)
            && ($filters['track'] === 'all' || in_array($filters['track'], $row['tracks'], true))
            && ($filters['search'] === '' || mb_stripos($row['name'].' '.$row['code'].' '.$row['program'], $filters['search']) !== false)));
        $perPage = max(1, (int) config('console.directory_per_page'));
        $page = min(max(1, (int) ($input['page'] ?? 1)), max(1, (int) ceil(count($visible) / $perPage)));
        $publicRows = array_map(static function (array $row): array {
            unset($row['attendance'], $row['discipline'], $row['open_request'], $row['frozen_reason']);

            return $row;
        }, array_slice($visible, ($page - 1) * $perPage, $perPage));

        return Inertia::render('Console/Followup', [
            'cases' => new LengthAwarePaginator($publicRows, count($visible), $perPage, $page, ['path' => route('console.followup'), 'query' => $filters]),
            'filters' => $filters, 'selected' => $selected, 'timezone' => $timezone,
            'counts' => ['students' => count(array_unique(array_column($queue, 'student_id'))), 'absence' => count(array_filter($queue, static fn (array $row): bool => $row['absences'] > 0 || $row['violations'] > 0)), 'held' => count(array_filter($queue, static fn (array $row): bool => $row['held']))],
            'window' => ['from' => $range['start']->toIso8601String(), 'until' => $range['end']->toIso8601String()],
            'requiresAssessment' => (bool) config('discipline.reactivation.requires_assessment'),
        ]);
    }

    public function update(FollowupActionRequest $request, string $enrollment): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        $reason = __('console_followup.actions.'.$data['action']).' · '.__('console_followup.contexts.'.$data['context']);
        if (trim($data['note'] ?? '') !== '') {
            $reason .= ' · '.trim($data['note']);
        }
        try {
            DB::transaction(function () use ($request, $enrollment, $organizationId, $actorId, $data, $reason): void {
                $record = Enrollment::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($enrollment);
                Gate::authorize('view', $record);
                abort_if($this->students->find($organizationId, (string) $record->student_profile_id) === null, 404);
                if ($record->status->value !== $data['expected_status']) {
                    throw ValidationException::withMessages(['action' => __('console_followup.stale')]);
                }
                $permission = match ($data['action']) {
                    'pause' => 'pause', 'freeze' => 'freeze', 'request' => 'requestReactivation', default => 'reactivate'
                };
                Gate::authorize($permission, $record);
                if ($data['action'] === 'pause') {
                    $timezone = (string) $this->context->forRequest($request)['school']['timezone'];
                    $today = CarbonImmutable::now($timezone)->startOfDay();
                    $return = CarbonImmutable::parse($data['return_date'], $timezone)->startOfDay();
                    if ($return->lt($today->addDays((int) config('discipline.voluntary_freeze.min_days'))) || $return->gt($today->addDays((int) config('discipline.voluntary_freeze.max_days')))) {
                        throw ValidationException::withMessages(['return_date' => __('console_followup.return_range', ['min' => config('discipline.voluntary_freeze.min_days'), 'max' => config('discipline.voluntary_freeze.max_days')])]);
                    }
                    app(PauseEnrollmentAction::class)->execute($record, $data['return_date'], $reason, $actorId);
                } elseif ($data['action'] === 'freeze') {
                    app(FreezeEnrollmentAction::class)->execute($record, $reason, 'manual', $actorId);
                } elseif ($data['action'] === 'resume') {
                    abort_unless($record->status === EnrollmentStatus::Paused, 422);
                    app(TransitionEnrollmentStatusAction::class)->execute($record, EnrollmentStatus::Active, $reason, $actorId);
                } elseif ($data['action'] === 'request') {
                    Gate::authorize('create', ReactivationRequest::class);
                    $created = app(CreateReactivationRequest::class)->execute(['organization_id' => $organizationId, 'enrollment_id' => $record->id, 'student_statement' => $reason]);
                    app(RequestReactivationAction::class)->execute($record, $reason, $actorId);
                    $this->auditRequest($organizationId, $actorId, $created, 'requested', $reason);
                } else {
                    $reactivation = ReactivationRequest::query()->forOrganization($organizationId)->forEnrollment((string) $record->id)->open()->lockForUpdate()->firstOrFail();
                    Gate::authorize('decide', $reactivation);
                    if ($data['action'] === 'assess') {
                        if ($record->status === EnrollmentStatus::Frozen) {
                            app(RequestReactivationAction::class)->execute($record, $reason, $actorId);
                        }
                        app(TransitionEnrollmentStatusAction::class)->execute($record, EnrollmentStatus::UnderAssessment, $reason, $actorId);
                    } else {
                        $approve = $data['action'] === 'approve';
                        $assessmentId = null;
                        if ($approve && (bool) config('discipline.reactivation.requires_assessment')) {
                            $assessment = collect($this->assessments->forRequest($organizationId, (string) $record->student_profile_id, (string) $reactivation->id))->firstWhere('id', $data['assessment_id'] ?? '');
                            if ($assessment === null || !$this->assessmentPassed($assessment)) {
                                throw ValidationException::withMessages(['assessment_id' => __('console_followup.assessment_required')]);
                            }
                            $assessmentId = $assessment->id;
                        }
                        if ($approve && $record->status === EnrollmentStatus::ReactivationRequested && !(bool) config('discipline.reactivation.requires_assessment')) {
                            app(TransitionEnrollmentStatusAction::class)->execute($record, EnrollmentStatus::UnderAssessment, $reason, $actorId);
                        }
                        $beforeRequest = ['status' => $reactivation->status->value, 'reviewer_id' => $reactivation->reviewer_id, 'assessment_attempt_id' => $reactivation->assessment_attempt_id];
                        app(DecideReactivationAction::class)->execute($reactivation, ['decision' => $approve ? ReactivationStatus::Approved : ReactivationStatus::Rejected, 'decision_note' => $reason, 'assessment_attempt_id' => $assessmentId]);
                        if ($approve) {
                            app(ReactivateEnrollmentAction::class)->execute($record, $reason, $actorId);
                        } else {
                            app(FreezeEnrollmentAction::class)->execute($record, $reason, 'manual', $actorId);
                        }
                        $this->auditRequest($organizationId, $actorId, $reactivation, $approve ? 'approved' : 'rejected', $reason, $beforeRequest);
                    }
                }
            });
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['action' => $error->getMessage()]);
        }

        return to_route('console.followup', ['enrollment' => $enrollment])->with('success', __('console_followup.saved'));
    }

    public function attendance(FollowupAttendanceRequest $request, string $enrollment, string $attendance): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        $reason = __('console_followup.correction_contexts.'.$data['context']);
        if (trim($data['note'] ?? '') !== '') {
            $reason .= ' · '.trim($data['note']);
        }
        try {
            DB::transaction(function () use ($organizationId, $actorId, $data, $reason, $enrollment, $attendance): void {
                $record = Enrollment::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($enrollment);
                Gate::authorize('view', $record);
                abort_if($this->students->find($organizationId, (string) $record->student_profile_id) === null, 404);
                $attendanceData = $this->attendance->findForOrganization($organizationId, $attendance);
                abort_if($attendanceData === null, 404);
                $participant = $this->participants->findForOrganization($organizationId, $attendanceData->sessionParticipantId);
                abort_if($participant === null || $participant->enrollmentId !== $enrollment || $participant->studentProfileId !== (string) $record->student_profile_id, 404);
                $attendanceRecord = Attendance::query()->lockForUpdate()->findOrFail($attendance);
                Gate::authorize('override', $attendanceRecord);
                if ($attendanceRecord->status->value !== $data['expected_status']) {
                    throw ValidationException::withMessages(['status' => __('console_followup.attendance_stale')]);
                }
                $violations = ViolationEvent::query()->forOrganization($organizationId)->forEnrollment($enrollment)
                    ->where('student_profile_id', $record->student_profile_id)->where('session_id', $participant->sessionId)
                    ->whereIn('type', [ViolationType::NoShow->value, ViolationType::UnexcusedAbsence->value])
                    ->notWaived()->orderBy('id')->lockForUpdate()->get();
                foreach ($violations as $violation) {
                    Gate::authorize('waive', $violation);
                }
                app(OverrideAttendanceAction::class)->execute($attendanceRecord, AttendanceStatus::from($data['status']), $reason, $actorId, $organizationId);
                foreach ($violations as $violation) {
                    app(WaiveViolationAction::class)->execute($violation, ['reason' => $reason]);
                    app(AuditRecorder::class)->record($organizationId, $actorId, 'user', 'console.followup.violation_waived', 'violation_events', (string) $violation->id,
                        ['waived_at' => null], ['waived_at' => $violation->waived_at?->toIso8601String(), 'waived_by' => $actorId], $reason);
                }
            });
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['status' => $error->getMessage()]);
        }

        return to_route('console.followup', ['enrollment' => $enrollment])->with('success', __('console_followup.attendance_saved'));
    }

    /** @return list<string> */
    private function availableActions(Enrollment $record, bool $hasRequest): array
    {
        $actions = [];
        if ($record->status === EnrollmentStatus::Active && Gate::allows('pause', $record)) {
            $actions[] = 'pause';
        }
        if ($record->status === EnrollmentStatus::Paused && Gate::allows('reactivate', $record)) {
            $actions[] = 'resume';
        }
        if (in_array($record->status, [EnrollmentStatus::Active, EnrollmentStatus::Paused], true) && Gate::allows('freeze', $record)) {
            $actions[] = 'freeze';
        }
        if ($record->status === EnrollmentStatus::Frozen && !$hasRequest && Gate::allows('requestReactivation', $record) && Gate::allows('create', ReactivationRequest::class)) {
            $actions[] = 'request';
        }
        if ($hasRequest && Gate::allows('reactivate', $record) && Gate::allows((string) config('discipline.reactivation.approver_permission'))) {
            if (in_array($record->status, [EnrollmentStatus::Frozen, EnrollmentStatus::ReactivationRequested], true)) {
                $actions[] = 'assess';
            }
            if ($record->status === EnrollmentStatus::UnderAssessment || ($record->status === EnrollmentStatus::ReactivationRequested && !(bool) config('discipline.reactivation.requires_assessment'))) {
                $actions[] = 'approve';
            }
            if (in_array($record->status, [EnrollmentStatus::UnderAssessment, EnrollmentStatus::ReactivationRequested], true)) {
                $actions[] = 'reject';
            }
        }

        return $actions;
    }

    private function assessmentPassed(ReactivationAssessmentData $assessment): bool
    {
        return $assessment->gradedAt !== null && $assessment->passed === true && $assessment->score !== null && $assessment->totalScore > 0
            && ($assessment->score / $assessment->totalScore * 100) >= (float) config('discipline.reactivation.passing_score_percent');
    }

    /** @param array<string, mixed>|null $before */
    private function auditRequest(string $organizationId, string $actorId, ReactivationRequest $request, string $action, string $reason, ?array $before = null): void
    {
        app(AuditRecorder::class)->record($organizationId, $actorId, 'user', 'console.followup.'.$action, 'reactivation_requests', (string) $request->id, $before, ['status' => $request->status->value, 'enrollment_id' => $request->enrollment_id, 'reviewer_id' => $request->reviewer_id, 'assessment_attempt_id' => $request->assessment_attempt_id], $reason);
    }
}
