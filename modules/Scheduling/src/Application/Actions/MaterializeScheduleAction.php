<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Data\MaterializationResult;
use Modules\Scheduling\Application\Services\ScheduleMaterializer;
use Modules\Scheduling\Domain\Models\Schedule;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class MaterializeScheduleAction
{
    public function __construct(
        private Transaction $transaction,
        private ScheduleMaterializer $materializer,
        private AuditRecorder $audit,
    ) {}

    public function execute(Schedule $schedule, string $actorId, string $reason): MaterializationResult
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }

        return $this->transaction->run(function () use ($schedule, $actorId, $reason): MaterializationResult {
            $result = $this->materializer->materialize($schedule, $actorId);
            $this->audit->record(
                organizationId: (string) $schedule->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_materialized',
                auditableType: 'schedules',
                auditableId: (string) $schedule->getKey(),
                oldValues: null,
                newValues: [
                    'created_sessions' => $result->created,
                    'availability_warnings' => $result->outsideAvailabilityWarnings,
                    'materialized_until' => $result->materializedUntil,
                ],
                reason: $reason,
            );

            return $result;
        });
    }
}
