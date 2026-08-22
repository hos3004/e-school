<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Providers;

use Modules\Staff\Application\Queries\TeacherQualificationQueryService;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
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
        ];
    }
}
