<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\AcademicReports\Domain\Events\SessionReportSubmitted;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
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
    ) {}

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
