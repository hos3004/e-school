<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Reporting\Domain\Events\StudentDashboardUpdated;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إسقاط تحديث على لوحة طالب — يُنشئ اللوحة عند أول ظهور ويحدّثها بعد.
 *
 * المقياس يأتي من config('reporting.projections') — لا أرقام سياسة هنا.
 * الترتيب: حراس ← معاملة ← نشر الحدث بعد النجاح.
 */
final readonly class UpdateStudentDashboardAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $delta enrollment_id · student_profile_id ·
     *                                    organization_id · metric · at؟
     */
    public function execute(array $delta): StudentDashboard
    {
        foreach (['organization_id', 'enrollment_id', 'student_profile_id', 'metric'] as $required) {
            if (!isset($delta[$required]) || (string) $delta[$required] === '') {
                throw BusinessRuleViolation::make(
                    'reporting.missing_projection_key',
                    'reporting::errors.missing_projection_key',
                    ['field' => $required],
                );
            }
        }

        /** @var StudentDashboard $dashboard */
        [$dashboard, $event] = $this->transaction->run(function () use ($delta): array {
            /** @var StudentDashboard|null $dashboard */
            $dashboard = StudentDashboard::query()
                ->where('enrollment_id', (string) $delta['enrollment_id'])
                ->lockForUpdate()
                ->first();

            if ($dashboard === null) {
                $dashboard = new StudentDashboard;
                $dashboard->fill([
                    'organization_id' => (string) $delta['organization_id'],
                    'enrollment_id' => (string) $delta['enrollment_id'],
                    'student_profile_id' => (string) $delta['student_profile_id'],
                ]);
            }

            $at = isset($delta['at'])
                ? CarbonImmutable::parse((string) $delta['at'], 'UTC')
                : null;

            match ((string) $delta['metric']) {
                'sessions_completed' => $this->bump($dashboard, 'sessions_total'),
                'sessions_attended' => $this->bump($dashboard, 'sessions_attended'),
                'sessions_missed' => $this->bump($dashboard, 'sessions_missed'),
                'violation_recorded' => $this->bump($dashboard, 'violations_count'),
                'freeze_recorded' => $this->bump($dashboard, 'freezes_count'),
                default => throw BusinessRuleViolation::make(
                    'reporting.unknown_student_metric',
                    'reporting::errors.unknown_student_metric',
                    ['metric' => (string) $delta['metric']],
                ),
            };

            if ($at !== null && in_array((string) $delta['metric'], ['sessions_completed', 'sessions_attended'], true)) {
                $dashboard->last_session_at = $at;
            }

            if ($at !== null && (string) $delta['metric'] === 'violation_recorded') {
                $dashboard->last_violation_at = $at;
            }

            $dashboard->recomputeAttendanceRate();
            $dashboard->save();

            return [$dashboard, new StudentDashboardUpdated(
                dashboardId: (string) $dashboard->getKey(),
                organizationId: (string) $dashboard->organization_id,
                enrollmentId: (string) $dashboard->enrollment_id,
                studentProfileId: (string) $dashboard->student_profile_id,
                sessionsAttended: (int) $dashboard->sessions_attended,
                sessionsMissed: (int) $dashboard->sessions_missed,
                attendanceRateBp: (int) $dashboard->attendance_rate_bp,
            )];
        });

        $this->events->dispatch($event);

        return $dashboard;
    }

    private function bump(StudentDashboard $dashboard, string $column): void
    {
        $dashboard->{$column} = max(0, (int) $dashboard->{$column} + 1);
    }
}
