<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\TeacherAvailability;

final readonly class ApproveTeacherAvailabilityAction
{
    public function __construct(
        private DecideTeacherAvailabilityAction $decideAvailability,
    ) {}

    public function execute(TeacherAvailability $availability, string $actorId, string $reason): TeacherAvailability
    {
        return $this->decideAvailability->execute(
            availability: $availability,
            decision: TeacherAvailabilityApprovalStatus::Approved,
            actorId: $actorId,
            reason: $reason,
        );
    }
}
