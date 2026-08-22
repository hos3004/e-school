<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\ValueObjects;

final readonly class CountryData
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public string $id,
        public string $iso2,
        public string $iso3,
        public array $name,
        public string $phoneCode,
        public bool $isActive,
        public int $sortOrder,
    ) {}
}
