<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BulkIndividualScheduleResult
{
    /**
     * @param array<string, string> $scheduleIdsByStudent
     * @param array<string, string> $failedStudents
     */
    public function __construct(
        public array $scheduleIdsByStudent,
        public array $failedStudents,
        public int $skippedCount,
    ) {}

    public function createdCount(): int
    {
        return count($this->scheduleIdsByStudent);
    }

    public function failedCount(): int
    {
        return count($this->failedStudents);
    }
}
