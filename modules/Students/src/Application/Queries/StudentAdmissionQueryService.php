<?php

declare(strict_types=1);

namespace Modules\Students\Application\Queries;

use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;

final readonly class StudentAdmissionQueryService implements StudentAdmissionQueries
{
    public function isClearedForAssignment(string $studentProfileId): bool
    {
        return RegistrationApplication::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereIn(
                'status',
                array_map(
                    static fn (RegistrationStatus $status): string => $status->value,
                    array_filter(
                        RegistrationStatus::cases(),
                        static fn (RegistrationStatus $status): bool => $status->isClearedForAssignment(),
                    ),
                ),
            )
            ->exists();
    }
}
