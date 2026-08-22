<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * مقبض تسجيل جاهز عند المزوّد — يُستخدم في الأرشفة.
 */
final readonly class RecordingHandle
{
    /**
     * @param list<array{type: string, url: string, length?: int}> $formats
     */
    public function __construct(
        public string $recordingId,
        public string $externalId,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public array $formats = [],
    ) {}
}
