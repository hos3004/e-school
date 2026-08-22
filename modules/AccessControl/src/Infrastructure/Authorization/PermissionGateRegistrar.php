<?php

declare(strict_types=1);

namespace Modules\AccessControl\Infrastructure\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;

/**
 * يسجّل صلاحيات قاعدة البيانات كقدرات Gate دون ربط AccessControl بنموذج Identity.
 */
final readonly class PermissionGateRegistrar
{
    public function __construct(
        private AccessControlQuerier $accessControl,
    ) {}

    public function register(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionNames = Permission::query()
            ->where('guard_name', GuardName::Web->value)
            ->orderBy('name')
            ->pluck('name');

        foreach ($permissionNames as $permissionName) {
            if (!is_string($permissionName)) {
                continue;
            }

            Gate::define(
                $permissionName,
                fn (Authenticatable $user): bool => $this->userHasPermission($user, $permissionName),
            );
        }
    }

    private function userHasPermission(Authenticatable $user, string $permissionName): bool
    {
        $identifier = $user->getAuthIdentifier();

        if (!is_string($identifier) && !is_int($identifier)) {
            return false;
        }

        $modelType = $user instanceof Model ? $user->getMorphClass() : $user::class;

        return $this->accessControl->modelHasPermission(
            $modelType,
            (string) $identifier,
            $permissionName,
            GuardName::Web->value,
        );
    }
}
