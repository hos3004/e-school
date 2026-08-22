<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Providers;

use Modules\Identity\Application\Policies\PasswordResetTokenPolicy;
use Modules\Identity\Application\Policies\UserDevicePolicy;
use Modules\Identity\Application\Policies\UserPolicy;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Infrastructure\Persistence\EloquentUserQueryService;
use Shared\Module\BaseModuleServiceProvider;

final class IdentityServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Identity';
    }

    /**
     * أحداث Identity تستهلكها موديولات أخرى (Audit، Notifications،
     * AccessControl). لا مستمعين داخليين حتى الآن — يُربطون هنا عند الحاجة.
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
            User::class => UserPolicy::class,
            UserDevice::class => UserDevicePolicy::class,
            PasswordResetToken::class => PasswordResetTokenPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            UserQueryService::class => EloquentUserQueryService::class,
        ];
    }
}
