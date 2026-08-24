<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\ValueObjects;

/**
 * سياق أكاديمي بدائي للتسكين؛ لا يسرّب نماذج Academics خارج الموديول.
 */
final readonly class PlacementAcademicContext
{
    public function __construct(
        public string $organizationId,
        public string $programId,
        public ?string $courseId,
        public ?string $sessionMode,
    ) {}
}
