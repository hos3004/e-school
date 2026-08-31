<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\ValueObjects;

final readonly class TeacherContractData
{
    public function __construct(
        public string $id,
        public string $basis,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?string $baseAmountMajor,
        public ?string $currency,
        public ?int $monthlyTargetSessions,
        public ?int $targetAdminTasks,
        public ?int $targetTrainingSessions,
    ) {}
}
