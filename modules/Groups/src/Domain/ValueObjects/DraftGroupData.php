<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

/** مجموعة مسودة أُنشئت للتو بالحد الأدنى من البيانات. */
final readonly class DraftGroupData
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public string $groupId,
        public string $organizationId,
        public string $code,
        public array $name,
        public string $status,
        public string $programId,
    ) {}
}
