<?php

declare(strict_types=1);

namespace App\Application\DTO;

/** نتيجة تسكين جماعي نجح كاملًا — الفشل يُرمى استثناءً ولا يُعاد جزئيًا. */
final readonly class BulkPlacementResult
{
    /**
     * @param list<string> $placedStudentProfileIds
     */
    public function __construct(
        public string $groupId,
        public string $groupLabel,
        public bool $groupWasCreated,
        public bool $groupIsDraft,
        public array $placedStudentProfileIds,
        public int $skippedExistingCount,
    ) {}

    public function placedCount(): int
    {
        return count($this->placedStudentProfileIds);
    }
}
