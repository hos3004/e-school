<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

final readonly class ProgramDigestRecipientData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $recipientType,
        public ?string $recipientUserId,
        public ?string $customEmail,
        public string $version,
    ) {}
}
