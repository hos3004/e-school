<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Contracts;

use Modules\Discipline\Domain\ValueObjects\ReactivationRequestData;

interface ReactivationRequestQueries
{
    public function find(string $reactivationRequestId): ?ReactivationRequestData;
}
