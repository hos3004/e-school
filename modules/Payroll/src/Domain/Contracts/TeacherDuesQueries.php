<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesStatement;

interface TeacherDuesQueries
{
    /** @return list<array<string, mixed>> */
    public function periods(Authenticatable $actor): array;

    public function statement(Authenticatable $actor, string $periodId): TeacherDuesStatement;
}
