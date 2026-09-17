<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\ValueObjects;

final readonly class GuardianDirectoryEntry
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $userId,
        public string $guardianCode,
        public bool $archived,
    ) {}
}
