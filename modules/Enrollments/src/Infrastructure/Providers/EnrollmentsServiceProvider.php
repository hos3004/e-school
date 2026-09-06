<?php

declare(strict_types=1);

namespace Modules\Enrollments\Infrastructure\Providers;

use Modules\Enrollments\Application\Policies\EnrollmentPolicy;
use Modules\Enrollments\Application\Policies\EnrollmentStatusHistoryPolicy;
use Modules\Enrollments\Application\Queries\EnrollmentAdministrationQueryService;
use Modules\Enrollments\Application\Services\EnrollmentPlacementService;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Shared\Module\BaseModuleServiceProvider;

final class EnrollmentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Enrollments';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Enrollment::class => EnrollmentPolicy::class,
            EnrollmentStatusHistory::class => EnrollmentStatusHistoryPolicy::class,
        ];
    }

    /** @return array<class-string, class-string> */
    protected function bindings(): array
    {
        return [
            EnrollmentAdministrationQueries::class => EnrollmentAdministrationQueryService::class,
            EnrollmentPlacementGateway::class => EnrollmentPlacementService::class,
        ];
    }
}
