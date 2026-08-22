<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * ما يُرجعه المزوّد بعد إنشاء الفصل — معرّفاته وأسراره عند المزوّد.
 */
final readonly class RemoteClassroom
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $externalId,
        public string $moderatorSecret,
        public string $attendeeSecret,
        public CarbonImmutable $createdAt,
        public array $meta = [],
    ) {}
}
