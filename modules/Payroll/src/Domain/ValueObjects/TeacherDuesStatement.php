<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\ValueObjects;

final readonly class TeacherDuesStatement
{
    /** @param array<string, mixed> $period
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $adjustments
     * @param list<array<string, mixed>> $teachers
     */
    public function __construct(public array $period, public array $entries, public array $adjustments, public array $teachers = []) {}
}
