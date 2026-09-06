<?php

declare(strict_types=1);

namespace Modules\Discipline\Infrastructure\Providers;

use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Discipline\Application\Listeners\RecordConfirmedAbsenceViolation;
use Modules\Discipline\Application\Policies\DisciplineActionPolicy;
use Modules\Discipline\Application\Policies\ReactivationRequestPolicy;
use Modules\Discipline\Application\Policies\ViolationEventPolicy;
use Modules\Discipline\Application\Queries\ReactivationRequestQueryService;
use Modules\Discipline\Domain\Contracts\ReactivationRequestQueries;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\Services\EscalationLadder;
use Shared\Module\BaseModuleServiceProvider;

final class DisciplineServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Discipline';
    }

    /**
     * ربط أحداث الموديول بمستمعيه — المستمعون من موديولات أخرى
     * يُسجَّلون في مزوّديها هم، لا هنا.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [
            AttendanceConfirmed::class => [RecordConfirmedAbsenceViolation::class],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            ViolationEvent::class => ViolationEventPolicy::class,
            DisciplineAction::class => DisciplineActionPolicy::class,
            ReactivationRequest::class => ReactivationRequestPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            EscalationLadder::class => EscalationLadder::class,
            ReactivationRequestQueries::class => ReactivationRequestQueryService::class,
        ];
    }
}
