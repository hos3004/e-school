<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Reporting\Domain\Events\TeacherDashboardUpdated;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

/**
 * إسقاط تحديث على لوحة معلم — يُنشئ اللوحة عند أول ظهور ويحدّثها بعد.
 *
 * المستحق يُجمَّع كأعداد صحيحة بالوحدات الصغرى عبر Money — لا float.
 * الترتيب: حراس ← معاملة ← نشر الحدث بعد النجاح.
 */
final readonly class UpdateTeacherDashboardAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $delta staff_profile_id · organization_id ·
     *                                    metric · amount_minor؟ · currency؟ · at؟
     */
    public function execute(array $delta): TeacherDashboard
    {
        foreach (['organization_id', 'staff_profile_id', 'metric'] as $required) {
            if (!isset($delta[$required]) || (string) $delta[$required] === '') {
                throw BusinessRuleViolation::make(
                    'reporting.missing_projection_key',
                    'reporting::errors.missing_projection_key',
                    ['field' => $required],
                );
            }
        }

        /** @var TeacherDashboard $dashboard */
        [$dashboard, $event] = $this->transaction->run(function () use ($delta): array {
            /** @var TeacherDashboard|null $dashboard */
            $dashboard = TeacherDashboard::query()
                ->where('staff_profile_id', (string) $delta['staff_profile_id'])
                ->lockForUpdate()
                ->first();

            if ($dashboard === null) {
                $dashboard = new TeacherDashboard;
                $dashboard->fill([
                    'organization_id' => (string) $delta['organization_id'],
                    'staff_profile_id' => (string) $delta['staff_profile_id'],
                    'currency' => (string) ($delta['currency'] ?? config('payroll.currency', 'EGP')),
                ]);
            }

            $at = isset($delta['at'])
                ? CarbonImmutable::parse((string) $delta['at'], 'UTC')
                : null;

            switch ((string) $delta['metric']) {
                case 'sessions_completed':
                    $this->bump($dashboard, 'sessions_total');
                    $this->bump($dashboard, 'sessions_completed');
                    if ($at !== null) {
                        $dashboard->last_session_at = $at;
                    }
                    break;

                case 'cancellation_by_self':
                    $this->bump($dashboard, 'sessions_total');
                    $this->bump($dashboard, 'cancellations_by_self');
                    break;

                case 'session_postponed':
                    $this->bump($dashboard, 'postponements');
                    break;

                case 'payout_credited':
                    $amountMinor = (int) ($delta['amount_minor'] ?? 0);
                    if ($amountMinor < 0) {
                        throw BusinessRuleViolation::make(
                            'reporting.negative_payout_delta',
                            'reporting::errors.negative_payout_delta',
                            ['amount_minor' => $amountMinor],
                        );
                    }
                    $total = Money::of((int) $dashboard->payout_minor, (string) $dashboard->currency)
                        ->plus(Money::of($amountMinor, (string) $dashboard->currency));
                    $dashboard->payout_minor = $total->minorUnits;
                    break;

                default:
                    throw BusinessRuleViolation::make(
                        'reporting.unknown_teacher_metric',
                        'reporting::errors.unknown_teacher_metric',
                        ['metric' => (string) $delta['metric']],
                    );
            }

            $dashboard->save();

            return [$dashboard, new TeacherDashboardUpdated(
                dashboardId: (string) $dashboard->getKey(),
                organizationId: (string) $dashboard->organization_id,
                staffProfileId: (string) $dashboard->staff_profile_id,
                sessionsCompleted: (int) $dashboard->sessions_completed,
                payoutMinor: (int) $dashboard->payout_minor,
            )];
        });

        $this->events->dispatch($event);

        return $dashboard;
    }

    private function bump(TeacherDashboard $dashboard, string $column): void
    {
        $dashboard->{$column} = max(0, (int) $dashboard->{$column} + 1);
    }
}
