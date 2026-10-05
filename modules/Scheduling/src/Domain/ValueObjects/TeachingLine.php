<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\ValueObjects;

/**
 * خط تدريس واحد لمعلم: جدول سارٍ لطالب أو لمجموعة، أو رابط تدريس معلق بلا موعد.
 *
 * يُقرأ للعرض فقط ولا يحمل نماذج Eloquent عبر حدود الموديول.
 */
final readonly class TeachingLine
{
    /** @param list<array{weekday: int, start_time: string}> $weeklySlots */
    public function __construct(
        public string $id,
        public string $courseId,
        public ?string $studentProfileId,
        public ?string $groupId,
        public string $sessionType,
        public array $weeklySlots = [],
        public ?int $durationMinutes = null,
        public ?string $timezone = null,
        public ?string $startsOn = null,
        public ?string $endsOn = null,
        public bool $awaitingSchedule = false,
    ) {}
}
