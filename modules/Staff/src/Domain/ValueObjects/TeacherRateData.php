<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\ValueObjects;

final readonly class TeacherRateData
{
    public function __construct(
        public string $id,
        public string $contractId,
        public string $scope,
        public string $amountMajor,
        public string $currency,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?string $programId,
        public ?string $courseId,
        public ?string $sessionType,
    ) {}
}
