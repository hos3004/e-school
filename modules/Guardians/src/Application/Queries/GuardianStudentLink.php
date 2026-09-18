<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Queries;

use Modules\Guardians\Domain\Enums\GuardianRelationship;

/**
 * ملخّص طالب مرتبط بوصي، للقراءة عبر حدود الموديول — قيَم بدائية فقط.
 */
final readonly class GuardianStudentLink
{
    /**
     * @param list<string> $visibleSections
     */
    public function __construct(
        public string $guardianLinkId,
        public string $studentProfileId,
        public GuardianRelationship $relationship,
        public bool $isPrimary,
        public bool $canActFor,
        public ?string $verifiedAt,
        public array $visibleSections,
    ) {}
}
