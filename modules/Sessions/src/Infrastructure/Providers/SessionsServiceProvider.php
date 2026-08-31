<?php

declare(strict_types=1);

namespace Modules\Sessions\Infrastructure\Providers;

use Modules\Sessions\Application\Policies\SessionParticipantPolicy;
use Modules\Sessions\Application\Policies\SessionPolicy;
use Modules\Sessions\Application\Policies\SessionStatusHistoryPolicy;
use Modules\Sessions\Application\Queries\SessionAdministrationQueryService;
use Modules\Sessions\Application\Queries\SessionFactsQueryService;
use Modules\Sessions\Application\Queries\SessionOperationsQueryService;
use Modules\Sessions\Application\Queries\SessionParticipantAdministrationQueryService;
use Modules\Sessions\Application\Queries\SessionSchedulingQueryService;
use Modules\Sessions\Application\Services\SessionSchedulingService;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Shared\Module\BaseModuleServiceProvider;

final class SessionsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Sessions';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            SessionAdministrationQueries::class => SessionAdministrationQueryService::class,
            SessionFactsQueries::class => SessionFactsQueryService::class,
            SessionSchedulingQueries::class => SessionSchedulingQueryService::class,
            SessionSchedulingGateway::class => SessionSchedulingService::class,
            SessionParticipantAdministrationQueries::class => SessionParticipantAdministrationQueryService::class,
        ];
    }

    /** @return array<class-string, class-string> */
    protected function scopedBindings(): array
    {
        return [SessionOperationsQueryService::class => SessionOperationsQueryService::class];
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            Session::class => SessionPolicy::class,
            SessionParticipant::class => SessionParticipantPolicy::class,
            SessionStatusHistory::class => SessionStatusHistoryPolicy::class,
        ];
    }
}
