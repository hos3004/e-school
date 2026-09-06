<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\AcademicReports\Domain\Events\SessionReportSubmitted;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تقديم تقرير الحصة من المعلم: رأس التقرير + تقييم كل طالب.
 *
 * الترتيب الإلزامي: حراس ← معاملة ← نشر الأحداث بعد النجاح.
 */
final readonly class SubmitSessionReportAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionAdministrationQueries $sessions,
        private SessionParticipantAdministrationQueries $participants,
        private AuditRecorder $audit,
    ) {}

    /**
     * Secure entry point for a teacher portal submission. Actor and tenant identifiers
     * are derived by the HTTP boundary and verified here through public Sessions contracts.
     *
     * @param list<array<string, mixed>> $students
     */
    public function executeForTeacher(
        string $organizationId,
        string $sessionId,
        string $staffProfileId,
        string $actorId,
        array $students,
        ?string $topicsCovered = null,
        ?string $generalNotes = null,
    ): SessionReport {
        $session = $this->sessions->findForOrganization($organizationId, $sessionId);

        if ($session === null) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.session_not_found',
                'academicreports::errors.session_report_session_not_found',
            );
        }

        if (!in_array($staffProfileId, array_filter([
            $session->staffProfileId,
            $session->originalStaffProfileId,
        ]), true)) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.teacher_not_assigned',
                'academicreports::errors.session_report_teacher_not_assigned',
            );
        }

        if (!in_array($session->status, [
            SessionStatus::InProgress->value,
            SessionStatus::AwaitingReview->value,
            SessionStatus::Completed->value,
        ], true)) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.invalid_session_state',
                'academicreports::errors.session_report_invalid_session_state',
                ['status' => $session->status],
            );
        }

        $expectedStudentIds = collect($this->participants->forSession($organizationId, $sessionId))
            ->filter(static fn ($participant): bool => $participant->invitationActive)
            ->pluck('studentProfileId')
            ->map(static fn (mixed $id): string => (string) $id)
            ->sort()
            ->values()
            ->all();
        $submittedStudentIds = collect($students)
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->sort()
            ->values()
            ->all();

        if ($expectedStudentIds === [] || $submittedStudentIds !== $expectedStudentIds) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.students_mismatch',
                'academicreports::errors.session_report_students_mismatch',
            );
        }

        $report = $this->execute(
            sessionId: $sessionId,
            staffProfileId: $staffProfileId,
            students: $students,
            sessionEndedAt: CarbonImmutable::parse($session->actualEnd ?? $session->scheduledEnd, 'UTC'),
            topicsCovered: $topicsCovered,
            generalNotes: $generalNotes,
        );

        $this->audit->record(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: 'user',
            action: 'academicreports.session_report_submitted',
            auditableType: 'session_reports',
            auditableId: (string) $report->getKey(),
            oldValues: null,
            newValues: [
                'session_id' => $sessionId,
                'staff_profile_id' => $staffProfileId,
                'student_count' => count($students),
            ],
            reason: (string) __('academicreports::messages.session_report_submitted'),
        );

        return $report;
    }

    /**
     * @param  list<array{
     *     student_profile_id: string,
     *     participation: int,
     *     performance: int,
     *     commitment: int,
     *     strengths?: ?string,
     *     weaknesses?: ?string,
     *     note?: ?string,
     * }>  $students
     * @param CarbonImmutable|null $sessionEndedAt نهاية الحصة الفعلية لحساب التأخير
     */
    public function execute(
        string $sessionId,
        string $staffProfileId,
        array $students,
        ?CarbonImmutable $submittedAt = null,
        ?CarbonImmutable $sessionEndedAt = null,
        ?string $topicsCovered = null,
        ?string $homeworkAssigned = null,
        ?string $generalNotes = null,
        ?string $supervisorPrivateNote = null,
        ?string $nextSessionPlan = null,
    ): SessionReport {
        $this->guardNotAlreadySubmitted($sessionId);
        $this->guardStudentsPayload($students);

        $submittedAt ??= CarbonImmutable::now('UTC');
        $isLate = $this->resolveIsLate($submittedAt, $sessionEndedAt);

        /** @var SessionReport $report */
        $report = $this->transaction->run(function () use (
            $sessionId,
            $staffProfileId,
            $students,
            $submittedAt,
            $isLate,
            $topicsCovered,
            $homeworkAssigned,
            $generalNotes,
            $supervisorPrivateNote,
            $nextSessionPlan,
        ): SessionReport {
            $report = SessionReport::query()->create([
                'session_id' => $sessionId,
                'staff_profile_id' => $staffProfileId,
                'topics_covered' => $topicsCovered,
                'homework_assigned' => $homeworkAssigned,
                'general_notes' => $generalNotes,
                'supervisor_private_note' => $supervisorPrivateNote,
                'next_session_plan' => $nextSessionPlan,
                'submitted_at' => $submittedAt,
                'is_late' => $isLate,
            ]);

            foreach ($students as $student) {
                SessionReportStudent::query()->create([
                    'session_report_id' => $report->id,
                    'student_profile_id' => $student['student_profile_id'],
                    'participation' => $student['participation'],
                    'performance' => $student['performance'],
                    'commitment' => $student['commitment'],
                    'strengths' => $student['strengths'] ?? null,
                    'weaknesses' => $student['weaknesses'] ?? null,
                    'note' => $student['note'] ?? null,
                ]);
            }

            return $report;
        });

        $this->events->dispatch(new SessionReportSubmitted(
            sessionReportId: $report->id,
            sessionId: $sessionId,
            staffProfileId: $staffProfileId,
            isLate: $isLate,
            studentCount: count($students),
            studentProfileIds: array_map(
                static fn (array $student): string => $student['student_profile_id'],
                $students,
            ),
        ));

        return $report->refresh();
    }

    private function guardNotAlreadySubmitted(string $sessionId): void
    {
        $exists = SessionReport::query()->where('session_id', $sessionId)->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.already_submitted',
                'academicreports::errors.session_report_already_submitted',
                ['session_id' => $sessionId],
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $students
     */
    private function guardStudentsPayload(array $students): void
    {
        if ($students === []) {
            throw BusinessRuleViolation::make(
                'academicreports.session_report.empty_students',
                'academicreports::errors.session_report_empty_students',
            );
        }

        $seen = [];

        foreach ($students as $student) {
            $id = (string) ($student['student_profile_id'] ?? '');

            if ($id === '' || isset($seen[$id])) {
                throw BusinessRuleViolation::make(
                    'academicreports.session_report.duplicate_student',
                    'academicreports::errors.session_report_duplicate_student',
                    ['student_profile_id' => $id],
                );
            }

            $seen[$id] = true;

            foreach (['participation', 'performance', 'commitment'] as $axis) {
                $score = (int) ($student[$axis] ?? 0);

                if ($score < SessionReportStudent::MIN_SCORE || $score > SessionReportStudent::MAX_SCORE) {
                    throw BusinessRuleViolation::make(
                        'academicreports.session_report.score_out_of_range',
                        'academicreports::errors.session_report_score_out_of_range',
                        ['min' => SessionReportStudent::MIN_SCORE, 'max' => SessionReportStudent::MAX_SCORE],
                    );
                }
            }
        }
    }

    private function resolveIsLate(CarbonImmutable $submittedAt, ?CarbonImmutable $sessionEndedAt): bool
    {
        if ($sessionEndedAt === null) {
            return false;
        }

        $slaHours = (int) config('academic.session_report.sla_hours', 0);
        $deadline = $sessionEndedAt->addHours($slaHours);

        return $submittedAt->gt($deadline);
    }
}
