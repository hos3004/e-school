<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\ValueObjects;

final readonly class FollowupEnrollmentData
{
    public function __construct(public string $id, public string $studentProfileId, public string $programId, public string $status, public ?string $expectedReturnDate, public ?string $frozenReason) {}
}
