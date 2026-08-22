<?php

declare(strict_types=1);

namespace Modules\AccessControl\Infrastructure\Providers;

use Modules\AccessControl\Application\Policies\PermissionPolicy;
use Modules\AccessControl\Application\Policies\RolePolicy;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Infrastructure\Persistence\AccessControlQueryService;
use Shared\Module\BaseModuleServiceProvider;

final class AccessControlServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'AccessControl';
    }

    /**
     * لا أحداث خارجية يستمع إليها هذا الموديول حاليًا؛ أحداثه هو من ينشرها.
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
            Role::class => RolePolicy::class,
            Permission::class => PermissionPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            AccessControlQuerier::class => AccessControlQueryService::class,
        ];
    }
}
