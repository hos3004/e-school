<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * لقطة بمشارك موجود الآن داخل الفصل — تُستخدم لحساب الحضور آليًا.
 */
final readonly class ParticipantSnapshot
{
    public function __construct(
        public string $externalUserId,
        public string $fullName,
        public JoinRole $role,
        public ?CarbonImmutable $joinedAt,
    ) {}
}
