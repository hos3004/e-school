<?php

declare(strict_types=1);

namespace Modules\AccessControl\Infrastructure\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;

/**
 * يسجّل صلاحيات قاعدة البيانات كقدرات Gate دون ربط AccessControl بنموذج Identity.
 */
final class PermissionGateRegistrar
{
    public function register(): void
    {
        // قد يُقلع التطبيق قبل وجود قاعدة بيانات أصلًا: composer install في CI،
        // أو بناء صورة Docker، أو قبل أول migrate. الصلاحيات عندئذ غير معرّفة،
        // وهذا ليس خطأ يستوجب إسقاط الإقلاع.
        try {
            if (!Schema::hasTable('permissions')) {
                return;
            }
        } catch (QueryException) {
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

        // Resolve inside the Gate callback so a long-running worker receives
        // the current request scope instead of capturing the boot-time object.
        return app(AccessControlQuerier::class)->modelHasPermission(
            $modelType,
            (string) $identifier,
            $permissionName,
            GuardName::Web->value,
        );
    }
}
