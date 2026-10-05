<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\ValueObjects;

final readonly class ReactivationAssessmentData
{
    /** @param array<string, mixed> $title */
    public function __construct(public string $id, public array $title, public ?int $score, public int $totalScore, public ?bool $passed, public ?string $gradedAt) {}
}
