<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Queries;

use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianRelationship;

/**
 * ملخّص وصي للقراءة عبر حدود الموديول — قيَم بدائية فقط.
 */
final readonly class GuardianSummary
{
    /**
     * @param  list<string>  $visibleSections
     */
    public function __construct(
        public string $guardianLinkId,
        public string $guardianProfileId,
        public string $userId,
        public GuardianRelationship $relationship,
        public bool $isPrimary,
        public bool $canActFor,
        public ?string $verifiedAt,
        public array $visibleSections,
        public ?ContactChannel $preferredContactChannel,
    ) {}
}
