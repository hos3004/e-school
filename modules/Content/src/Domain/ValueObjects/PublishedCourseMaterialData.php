<?php

declare(strict_types=1);

namespace Modules\Content\Domain\ValueObjects;

/** Public metadata only; storage disk/path and external destinations stay server-side. */
final readonly class PublishedCourseMaterialData
{
    /** @param array<string, string> $title @param array<string, string> $description */
    public function __construct(
        public string $id, public string $courseId, public array $title, public array $description,
        public string $type, public ?int $sizeBytes, public int $revision, public ?string $publishedAt,
    ) {}
}
