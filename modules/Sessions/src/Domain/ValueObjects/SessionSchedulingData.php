<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/** حقائق الحصة التي يحتاجها محرك الجدولة والتأجيل فقط. */
final readonly class SessionSchedulingData
{
    /**
     * @param array<string, string> $title
     * @param list<string> $studentProfileIds
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public ?string $scheduleId,
        public ?string $groupId,
        public string $courseId,
        public string $staffProfileId,
        public string $sessionType,
        public string $status,
        public CarbonImmutable $scheduledStart,
        public CarbonImmutable $scheduledEnd,
        public array $title,
        public array $studentProfileIds,
    ) {}
}
