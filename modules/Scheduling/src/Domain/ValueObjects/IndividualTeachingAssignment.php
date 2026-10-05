<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\ValueObjects;

final readonly class IndividualTeachingAssignment
{
    public function __construct(public string $id, public string $studentProfileId, public string $courseId, public string $sessionType, public bool $awaitingSchedule = false) {}
}
