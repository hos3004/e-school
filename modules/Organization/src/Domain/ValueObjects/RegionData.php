<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\ValueObjects;

final readonly class RegionData
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public string $id,
        public string $countryId,
        public string $code,
        public array $name,
        public bool $isActive,
        public int $sortOrder,
    ) {}
}
