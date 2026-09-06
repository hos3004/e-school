<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface TeacherDuesOperations
{
    /** @param array<string, mixed> $data
     * @return array{periodId: string, staffProfileId: string, adjustmentId: string}
     */
    public function propose(Authenticatable $actor, string $periodId, array $data): array;

    /** @return array{periodId: string, staffProfileId: string, adjustmentId: string} */
    public function decide(Authenticatable $actor, string $adjustmentId, bool $approve, string $reason): array;
}
