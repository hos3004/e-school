<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RolePermissionsSynced;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

/**
 * مزامنة صلاحيات الدور مع قائمة معطاة — الهدف هو الحالة النهائية كاملة.
 *
 * أدوار النظام مقفلة: تعديل صلاحياتها يتم عبر هجرات/بيانات أولية فقط.
 * كل أسماء الصلاحيات يجب أن توجد وأن تطابق حارس الدور.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class SyncRolePermissionsAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function execute(
        string $roleId,
        array $permissionNames,
        ?string $actorId = null,
    ): void {
        /** @var Role|null $role */
        $role = Role::query()->find($roleId);

        if ($role === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.not_found',
                'accesscontrol::errors.role_not_found',
            );
        }

        if ($role->is_system) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.system_locked',
                'accesscontrol::errors.role_system_locked',
                ['name' => $role->name],
            );
        }

        $permissionNames = array_values(array_unique($permissionNames));
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->get();

        $this->guard($role, $permissionNames, $permissions);

        /** @var list<string> $attached */
        $attached = [];
        /** @var list<string> $detached */
        $detached = [];

        DB::transaction(function () use ($role, $permissions, &$attached, &$detached): void {
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $role->getKey())
                ->pluck('permission_id')
                ->all();

            $target = $permissions->modelKeys();

            foreach (array_diff($target, $existing) as $toAttach) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => (string) $role->getKey(),
                    'permission_id' => $toAttach,
                ]);

                $attached[] = $toAttach;
            }

            foreach (array_diff($existing, $target) as $toDetach) {
                DB::table('role_has_permissions')
                    ->where('role_id', $role->getKey())
                    ->where('permission_id', $toDetach)
                    ->delete();

                $detached[] = (string) $toDetach;
            }
        });

        if ($attached !== [] || $detached !== []) {
            $this->events->dispatch(new RolePermissionsSynced(
                roleId: (string) $role->getKey(),
                permissionIds: $permissions->modelKeys(),
                attached: $attached,
                detached: $detached,
                actorId: $actorId,
            ));
        }
    }

    /**
     * @param  list<string>  $permissionNames
     * @param  Collection<int, Permission>  $permissions
     */
    private function guard(Role $role, array $permissionNames, Collection $permissions): void
    {
        if (count($permissions) !== count($permissionNames)) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_found',
                'accesscontrol::errors.permission_not_found',
            );
        }

        foreach ($permissions as $permission) {
            if ($permission->guard_name !== $role->guard_name) {
                throw BusinessRuleViolation::make(
                    'accesscontrol.permission.guard_mismatch',
                    'accesscontrol::errors.guard_mismatch',
                    ['name' => $permission->name],
                );
            }
        }
    }
}
