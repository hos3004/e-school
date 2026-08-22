<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\PermissionDeleted;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Shared\Support\BusinessRuleViolation;

/**
 * حذف صلاحية — مرفوض ما دامت مرتبطة بأدوار أو منوحة مباشرة لنماذج.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class DeletePermissionAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(string $permissionId, ?string $actorId = null): void
    {
        /** @var Permission|null $permission */
        $permission = Permission::query()->find($permissionId);

        if ($permission === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_found',
                'accesscontrol::errors.permission_not_found',
            );
        }

        $this->guard($permission);

        DB::transaction(function () use ($permission): void {
            $permission->delete();
        });

        $this->events->dispatch(new PermissionDeleted(
            permissionId: (string) $permission->getKey(),
            name: $permission->name,
            actorId: $actorId,
        ));
    }

    private function guard(Permission $permission): void
    {
        $inRoles = DB::table('role_has_permissions')
            ->where('permission_id', $permission->getKey())
            ->exists();

        if ($inRoles) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.in_use_by_roles',
                'accesscontrol::errors.permission_in_use',
                ['name' => $permission->name],
            );
        }

        $direct = ModelHasPermission::query()
            ->where('permission_id', $permission->getKey())
            ->exists();

        if ($direct) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.in_use_directly',
                'accesscontrol::errors.permission_in_use',
                ['name' => $permission->name],
            );
        }
    }
}
