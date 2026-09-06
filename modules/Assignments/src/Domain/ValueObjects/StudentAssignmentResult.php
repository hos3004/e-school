<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\ValueObjects;

/** Educational result for one student; never contains classmates, attachment paths or administrative data. */
final readonly class StudentAssignmentResult
{
    /** @param array<string,string> $title */
    public function __construct(
        public string $id,
        public string $courseId,
        public array $title,
        public string $dueAt,
        public string $submissionStatus,
        public string $submissionStatusLabel,
        public ?string $submittedAt,
        public ?string $submissionContent,
        public ?string $gradedAt,
        public ?int $score,
        public int $maxScore,
        public ?string $feedback,
    ) {}
}
