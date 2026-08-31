<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\ScheduleMaterializer;
use Modules\Scheduling\Domain\Events\ScheduleChanged;
use Modules\Scheduling\Domain\Models\Schedule;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ActivateScheduleAction
{
    public function __construct(
        private Transaction $transaction,
        private ScheduleMaterializer $materializer,
        private AuditRecorder $audit,
        private Dispatcher $events,
    ) {}

    public function execute(Schedule $schedule, string $actorId, string $reason): Schedule
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if ($schedule->is_active) {
            return $schedule;
        }

        $schedule = $this->transaction->run(function () use ($schedule, $actorId, $reason): Schedule {
            $schedule->forceFill(['is_active' => true])->save();
            $materialized = $this->materializer->materialize($schedule, $actorId);
            $this->audit->record(
                organizationId: (string) $schedule->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_activated',
                auditableType: 'schedules',
                auditableId: (string) $schedule->getKey(),
                oldValues: ['is_active' => false],
                newValues: ['is_active' => true, 'created_sessions' => $materialized->created],
                reason: $reason,
            );

            return $schedule;
        });

        $this->events->dispatch(new ScheduleChanged((string) $schedule->getKey(), now('UTC')->toIso8601String(), $actorId));

        return $schedule;
    }
}
