<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\ValueObjects;

final readonly class AcademicCatalogItemData
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?string $programId = null,
        public ?string $sessionMode = null,
    ) {}
}
