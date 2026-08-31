<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Providers;

use Modules\Staff\Application\Policies\StaffProfilePolicy;
use Modules\Staff\Application\Policies\TeacherAvailabilityPolicy;
use Modules\Staff\Application\Queries\StaffAdministrationQueryService;
use Modules\Staff\Application\Queries\StaffQueryService;
use Modules\Staff\Application\Queries\TeacherDirectoryQueryService;
use Modules\Staff\Application\Queries\TeacherQualificationQueryService;
use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Infrastructure\Persistence\DbTeacherRateResolver;
use Shared\Module\BaseModuleServiceProvider;

final class StaffServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Staff';
    }

    protected function bindings(): array
    {
        return [
            StaffAdministrationQueries::class => StaffAdministrationQueryService::class,
            TeacherQualificationQueries::class => TeacherQualificationQueryService::class,
            StaffQueries::class => StaffQueryService::class,
            TeacherDirectoryQueries::class => TeacherDirectoryQueryService::class,
            // كان معرَّفًا بلا ربط، فلا يستطيع Payroll حلّ سعر حصة إطلاقًا.
            TeacherRateResolver::class => DbTeacherRateResolver::class,
        ];
    }

    protected function policies(): array
    {
        return [
            StaffProfile::class => StaffProfilePolicy::class,
            TeacherAvailability::class => TeacherAvailabilityPolicy::class,
        ];
    }
}
