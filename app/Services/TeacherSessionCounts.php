<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;

final readonly class TeacherSessionCounts
{
    public function __construct(private SessionAdministrationQueries $sessions) {}

    /** @return array{month:string,upcoming:int,completed:int,cancelled:int} */
    public function forTeacher(string $organizationId, string $staffId, string $timezone): array
    {
        $month = CarbonImmutable::now($timezone)->startOfMonth();
        $counts = $this->sessions->countsForTeachers($organizationId, [$staffId], $month->utc(), $month->addMonth()->utc());

        return ['month' => $month->format('Y-m'), ...($counts[$staffId] ?? ['upcoming' => 0, 'completed' => 0, 'cancelled' => 0])];
    }
}
