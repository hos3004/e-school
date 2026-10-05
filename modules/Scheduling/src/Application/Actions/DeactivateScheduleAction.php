<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\Events\ScheduleChanged;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class DeactivateScheduleAction
{
    public function __construct(
        private Transaction $transaction,
        private SessionSchedulingGateway $sessions,
        private AuditRecorder $audit,
        private Dispatcher $events,
    ) {}

    public function execute(Schedule $schedule, string $actorId, string $reason): Schedule
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if (!$schedule->is_active) {
            return $schedule;
        }

        $cutoff = CarbonImmutable::now('UTC')->addHours((int) config('scheduling.recurrence.edit_lock_hours'));
        $schedule = $this->transaction->run(function () use ($schedule, $actorId, $reason, $cutoff): Schedule {
            $superseded = $this->sessions->supersedeFutureForSchedule(
                (string) $schedule->organization_id,
                (string) $schedule->getKey(),
                $cutoff,
                $actorId,
                $reason,
            );
            $schedule->forceFill(['is_active' => false])->save();
            $this->audit->record(
                organizationId: (string) $schedule->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_deactivated',
                auditableType: 'schedules',
                auditableId: (string) $schedule->getKey(),
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false, 'superseded_sessions' => $superseded],
                reason: $reason,
            );

            return $schedule;
        });

        $this->events->dispatch(new ScheduleChanged((string) $schedule->getKey(), $cutoff->toIso8601String(), $actorId));

        return $schedule;
    }
}
