<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Contracts;

use Modules\Groups\Domain\ValueObjects\GroupPlacementData;

interface GroupPlacementGateway
{
    public function placeStudent(
        string $organizationId,
        string $groupId,
        string $programId,
        ?string $courseId,
        string $studentProfileId,
        bool $requiresSingleMember,
    ): GroupPlacementData;
}
