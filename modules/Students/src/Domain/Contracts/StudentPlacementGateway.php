<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Contracts;

use Modules\Students\Domain\ValueObjects\StudentPlacementData;

interface StudentPlacementGateway
{
    public function findCleared(string $studentProfileId, ?string $applicationId = null): ?StudentPlacementData;

    public function markAssigned(string $organizationId, string $studentProfileId, ?string $applicationId = null): void;
}
