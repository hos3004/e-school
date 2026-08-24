<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleDeleted;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

/**
 * حذف دور — مرفوض لأدوار النظام، ومرفوض ما دام مسندًا لأي نموذج.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class DeleteRoleAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(
        string $roleId,
        ?string $actorId = null,
        ?string $organizationId = null,
    ): void {
        /** @var Role|null $role */
        $role = Role::query()
            ->when($organizationId !== null, fn (Builder $query): Builder => $query->forOrganization($organizationId))
            ->find($roleId);

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

        $this->guardAssignments($role);

        DB::transaction(function () use ($role): void {
            DB::table('role_has_permissions')
                ->where('role_id', $role->getKey())
                ->delete();

            $role->delete();
        });

        $this->events->dispatch(new RoleDeleted(
            roleId: (string) $role->getKey(),
            name: $role->name,
            actorId: $actorId,
        ));
    }

    private function guardAssignments(Role $role): void
    {
        $assigned = ModelHasRole::query()
            ->where('role_id', $role->getKey())
            ->exists();

        if ($assigned) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.in_use',
                'accesscontrol::errors.role_in_use',
                ['name' => $role->name],
            );
        }
    }
}
