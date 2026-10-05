<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

use Modules\Staff\Domain\ValueObjects\TeacherAvailabilityData;
use Modules\Staff\Domain\ValueObjects\TeacherContractData;
use Modules\Staff\Domain\ValueObjects\TeacherRateData;

interface StaffAdministrationQueries
{
    /** @return list<TeacherAvailabilityData> */
    public function availabilityForTeacher(string $organizationId, string $staffProfileId): array;

    /** @return list<TeacherContractData> */
    public function contractsForTeacher(string $organizationId, string $staffProfileId): array;

    /** @return list<TeacherRateData> */
    public function ratesForTeacher(string $organizationId, string $staffProfileId): array;
}
