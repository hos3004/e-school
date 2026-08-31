<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Data;

final readonly class MaterializationResult
{
    public function __construct(
        public int $created,
        public int $outsideAvailabilityWarnings,
        public string $materializedUntil,
    ) {}
}
