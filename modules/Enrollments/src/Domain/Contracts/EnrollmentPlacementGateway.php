<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Contracts;

use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;

interface EnrollmentPlacementGateway
{
    public function activate(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ): EnrollmentPlacementData;
}
