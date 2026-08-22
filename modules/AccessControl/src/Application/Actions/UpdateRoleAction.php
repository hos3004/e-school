<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleUpdated;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

/**
 * تعديل دور — أدوار النظام (is_system) مقفلة أمام التعديل نهائيًا،
 * والاسم لا يتعارض داخل نطاق المنظمة والحارس نفسه.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class UpdateRoleAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(
        string $roleId,
        ?string $name = null,
        ?string $organizationId = null,
        ?string $actorId = null,
    ): Role {
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

        $this->guardDuplicateName($role, $name);

        $changes = [];

        /** @var Role $role */
        $role = DB::transaction(function () use ($role, $name, $organizationId, &$changes): Role {
            if ($name !== null && $name !== $role->name) {
                $changes['name'] = ['from' => $role->name, 'to' => $name];
                $role->name = $name;
            }

            if ($organizationId !== null && $organizationId !== $role->organization_id) {
                $changes['organization_id'] = ['from' => $role->organization_id, 'to' => $organizationId];
                $role->organization_id = $organizationId;
            }

            if ($changes !== []) {
                $role->save();
            }

            return $role;
        });

        if ($changes !== []) {
            $this->events->dispatch(new RoleUpdated(
                roleId: (string) $role->getKey(),
                name: $role->name,
                changed: $changes,
                actorId: $actorId,
            ));
        }

        return $role;
    }

    private function guardDuplicateName(Role $role, ?string $name): void
    {
        if ($name === null || $name === $role->name) {
            return;
        }

        $exists = Role::query()
            ->where('name', $name)
            ->where('guard_name', $role->guard_name->value)
            ->when(
                $role->organization_id === null,
                fn (Builder $q): Builder => $q->whereNull('organization_id'),
                fn (Builder $q): Builder => $q->where('organization_id', $role->organization_id),
            )
            ->whereKeyNot($role->getKey())
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.name_taken',
                'accesscontrol::errors.role_name_taken',
                ['name' => $name],
            );
        }
    }
}
