<?php

declare(strict_types=1);

namespace Modules\Guardians\Infrastructure\Providers;

use Modules\Guardians\Application\Listeners\DeactivateLinksWhenGuardianArchived;
use Modules\Guardians\Application\Policies\GuardianLinkPolicy;
use Modules\Guardians\Application\Policies\GuardianProfilePolicy;
use Modules\Guardians\Application\Queries\GuardianQueryService;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Guardians\Domain\Events\GuardianProfileArchived;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Module\BaseModuleServiceProvider;

final class GuardiansServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Guardians';
    }

    /**
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [
            GuardianProfileArchived::class => [
                DeactivateLinksWhenGuardianArchived::class,
            ],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            GuardianProfile::class => GuardianProfilePolicy::class,
            GuardianLink::class => GuardianLinkPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            GuardianQuery::class => GuardianQueryService::class,
        ];
    }
}
