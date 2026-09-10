<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Providers;

use Modules\Groups\Domain\Events\StudentAssignedToGroup;
use Modules\Groups\Domain\Events\StudentLeftGroup;
use Modules\Scheduling\Application\Listeners\SyncStudentAssignedToGroupSessions;
use Modules\Scheduling\Application\Listeners\SyncStudentLeftGroupSessions;
use Modules\Scheduling\Application\Policies\PendingTeachingAssignmentPolicy;
use Modules\Scheduling\Application\Policies\PostponementRequestPolicy;
use Modules\Scheduling\Application\Policies\ScheduleChangeRequestPolicy;
use Modules\Scheduling\Application\Policies\SchedulePolicy;
use Modules\Scheduling\Application\Queries\IndividualTeachingAssignmentQueries;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Shared\Module\BaseModuleServiceProvider;

final class SchedulingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Scheduling';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            Schedule::class => SchedulePolicy::class,
            PendingTeachingAssignment::class => PendingTeachingAssignmentPolicy::class,
            PostponementRequest::class => PostponementRequestPolicy::class,
            ScheduleChangeRequest::class => ScheduleChangeRequestPolicy::class,
        ];
    }

    /** @return array<class-string, class-string> */
    protected function scopedBindings(): array
    {
        return [
            SchedulingAdministrationQueryService::class => SchedulingAdministrationQueryService::class,
            IndividualTeachingAssignments::class => IndividualTeachingAssignmentQueries::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    protected function listeners(): array
    {
        return [
            StudentAssignedToGroup::class => [SyncStudentAssignedToGroupSessions::class],
            StudentLeftGroup::class => [SyncStudentLeftGroupSessions::class],
        ];
    }
}
