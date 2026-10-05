<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Modules\Scheduling\Domain\Enums\ScheduleChangeStatus;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Shared\Support\BusinessRuleViolation;

/** سحب المعلم طلب تغيير الموعد الدائم قبل اكتمال ردود الطلاب. */
final readonly class WithdrawScheduleChange
{
    public function __construct(private RespondToScheduleChange $responses) {}

    public function execute(ScheduleChangeRequest $request, string $actorId): ScheduleChangeRequest
    {
        $schedule = $request->schedule()->first();
        if (!$schedule instanceof Schedule) {
            throw BusinessRuleViolation::make('scheduling.schedule_not_found', 'scheduling::errors.schedule_not_found');
        }

        return $this->responses->close($request, $schedule, $actorId, ScheduleChangeStatus::Withdrawn, 'withdrawn');
    }
}
