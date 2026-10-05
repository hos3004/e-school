<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BulkIndividualSchedulePreview
{
    /**
     * @param list<string> $eligibleStudentIds
     * @param list<string> $assignedStartTimes
     */
    public function __construct(
        public int $selectedCount,
        public array $eligibleStudentIds,
        public int $availableSlotCount,
        public array $assignedStartTimes,
    ) {}

    public function eligibleCount(): int
    {
        return count($this->eligibleStudentIds);
    }

    public function blockedCount(): int
    {
        return $this->selectedCount - $this->eligibleCount();
    }

    public function hasEnoughSlots(): bool
    {
        return count($this->assignedStartTimes) >= $this->eligibleCount();
    }
}
