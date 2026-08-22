<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * مواصفات إنشاء الفصل عند المزوّد — قيَم فقط، بلا نماذج.
 */
final readonly class ClassroomSpec
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $sessionId,
        public string $externalMeetingId,
        public string $title,
        public ?CarbonImmutable $startsAt,
        public int $maxParticipants,
        public bool $recordable = false,
        public array $meta = [],
    ) {}
}
