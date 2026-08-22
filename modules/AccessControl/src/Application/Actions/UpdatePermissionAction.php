<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\PermissionUpdated;
use Modules\AccessControl\Domain\Models\Permission;
use Shared\Support\BusinessRuleViolation;

/**
 * تعديل صلاحية — الاسم والحارس مفتاح الهوية المنطقية فلا يتغير الحارس،
 * والاسم لا يتعارض مع صلاحية أخرى على نفس الحارس.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class UpdatePermissionAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, string>|null  $description
     */
    public function execute(
        string $permissionId,
        ?string $name = null,
        ?string $module = null,
        ?array $description = null,
        ?string $actorId = null,
    ): Permission {
        /** @var Permission|null $permission */
        $permission = Permission::query()->find($permissionId);

        if ($permission === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_found',
                'accesscontrol::errors.permission_not_found',
            );
        }

        $this->guard($permission, $name);

        $changes = [];

        /** @var Permission $permission */
        $permission = DB::transaction(function () use ($permission, $name, $module, $description, &$changes): Permission {
            if ($name !== null && $name !== $permission->name) {
                $changes['name'] = ['from' => $permission->name, 'to' => $name];
                $permission->name = $name;
            }

            if ($module !== null && $module !== $permission->module) {
                $changes['module'] = ['from' => $permission->module, 'to' => $module];
                $permission->module = $module;
            }

            if ($description !== null && $description !== $permission->description) {
                $changes['description'] = ['from' => $permission->description, 'to' => $description];
                $permission->description = $description;
            }

            $permission->save();

            return $permission;
        });

        if ($changes !== []) {
            $this->events->dispatch(new PermissionUpdated(
                permissionId: (string) $permission->getKey(),
                name: $permission->name,
                changed: $changes,
                actorId: $actorId,
            ));
        }

        return $permission;
    }

    private function guard(Permission $permission, ?string $name): void
    {
        if ($name === null || $name === $permission->name) {
            return;
        }

        $exists = Permission::query()
            ->where('name', $name)
            ->where('guard_name', $permission->guard_name->value)
            ->whereKeyNot($permission->getKey())
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.name_taken',
                'accesscontrol::errors.permission_name_taken',
                ['name' => $name],
            );
        }
    }
}
