<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\ValueObjects;

final readonly class FollowupDisciplineData
{
    /** @param list<array<string, mixed>> $violations
     * @param list<array<string, mixed>> $actions
     * @param list<array<string, mixed>> $requests */
    public function __construct(public array $violations, public array $actions, public array $requests) {}
}
