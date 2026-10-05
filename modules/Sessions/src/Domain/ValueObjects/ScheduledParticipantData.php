<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

/** هوية مشاركة تُنشأ مع الحصة المولدة من قالب الجدول. */
final readonly class ScheduledParticipantData
{
    public function __construct(
        public string $studentProfileId,
        public string $enrollmentId,
    ) {}
}
