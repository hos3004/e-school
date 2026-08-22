<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Providers;

use Modules\Organization\Application\Policies\AcademicCalendarPolicy;
use Modules\Organization\Application\Policies\HolidayPolicy;
use Modules\Organization\Application\Policies\OrganizationPolicy;
use Modules\Organization\Application\Queries\GeographyQueryService;
use Modules\Organization\Application\Queries\OrganizationSettingQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;
use Shared\Module\BaseModuleServiceProvider;

final class OrganizationServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Organization';
    }

    /**
     * ربط Domain Events بمستمعيها — موديول Organization هو جذر المنصة
     * فلا يستمع لأحداث موديولات أخرى، وأحداثه تُستهلك خارجيًا.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Organization::class => OrganizationPolicy::class,
            AcademicCalendar::class => AcademicCalendarPolicy::class,
            Holiday::class => HolidayPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            GeographyQueries::class => GeographyQueryService::class,
            OrganizationSettingQueries::class => OrganizationSettingQueryService::class,
        ];
    }
}
