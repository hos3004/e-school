<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Providers;

use Modules\Staff\Application\Policies\StaffProfilePolicy;
use Modules\Staff\Application\Queries\StaffQueryService;
use Modules\Staff\Application\Queries\TeacherQualificationQueryService;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Models\StaffProfile;
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
            TeacherQualificationQueries::class => TeacherQualificationQueryService::class,
            StaffQueries::class => StaffQueryService::class,
            // كان معرَّفًا بلا ربط، فلا يستطيع Payroll حلّ سعر حصة إطلاقًا.
            TeacherRateResolver::class => DbTeacherRateResolver::class,
        ];
    }

    protected function policies(): array
    {
        return [
            StaffProfile::class => StaffProfilePolicy::class,
        ];
    }
}
