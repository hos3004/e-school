<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

use Carbon\CarbonImmutable;

interface SessionPayCatalog
{
    /** @return list<array{id: string, name: string, session_type: string, duration_minutes: int, amount: int, currency: string}> */
    public function rates(string $organizationId, ?CarbonImmutable $at = null): array;

    /** @return list<int> */
    public function durations(string $organizationId, string $sessionType): array;
}
