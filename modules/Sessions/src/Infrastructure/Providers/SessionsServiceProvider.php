<?php

declare(strict_types=1);

namespace Modules\Sessions\Infrastructure\Providers;

use Modules\Sessions\Application\Queries\SessionFactsQueryService;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
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
            SessionFactsQueries::class => SessionFactsQueryService::class,
        ];
    }
}
