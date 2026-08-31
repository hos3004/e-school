<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomAdministrationData;

interface ClassroomAdministrationQueries
{
    public function findForSession(
        string $organizationId,
        string $sessionId,
    ): ?ClassroomAdministrationData;

    /** @return array<string, int> */
    public function summaryForOrganization(string $organizationId): array;
}
