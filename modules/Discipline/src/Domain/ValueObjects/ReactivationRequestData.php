<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\ValueObjects;

final readonly class ReactivationRequestData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $requestedBy,
        public bool $canStartAssessment,
    ) {}
}
