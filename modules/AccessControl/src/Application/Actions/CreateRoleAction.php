<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\RoleCreated;
use Modules\AccessControl\Domain\Models\Role;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء دور داخل منظمة (أو دور عام بلا منظمة).
 *
 * الأدوار تُنشأ دائمًا بلا صلاحيات؛ ربط الصلاحيات يتم عبر
 * SyncRolePermissionsAction بعد الإنشاء.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class CreateRoleAction
{
    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $name,
        GuardName $guard,
        ?string $organizationId = null,
        bool $isSystem = false,
        ?string $actorId = null,
        ?string $reason = null,
    ): Role {
        $this->guard($name, $guard, $organizationId);

        /** @var Role $role */
        $role = DB::transaction(function () use ($name, $guard, $organizationId, $isSystem, $actorId, $reason): Role {
            /** @var Role $created */
            $created = Role::query()->create([
                'organization_id' => $organizationId,
                'name' => $name,
                'guard_name' => $guard,
                'is_system' => $isSystem,
            ]);

            if ($actorId !== null && $organizationId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'accesscontrol.role_created',
                    auditableType: 'roles',
                    auditableId: (string) $created->getKey(),
                    oldValues: null,
                    newValues: [
                        'name' => $created->name,
                        'guard_name' => $created->guard_name->value,
                    ],
                    reason: $reason,
                );
            }

            return $created;
        });

        $this->events->dispatch(new RoleCreated(
            roleId: (string) $role->getKey(),
            organizationId: $role->organization_id,
            name: $role->name,
            guardName: $role->guard_name->value,
            isSystem: $role->is_system,
            actorId: $actorId,
        ));

        return $role;
    }

    private function guard(string $name, GuardName $guard, ?string $organizationId): void
    {
        $exists = Role::query()
            ->where('name', $name)
            ->where('guard_name', $guard->value)
            ->when(
                $organizationId === null,
                fn (Builder $q): Builder => $q->whereNull('organization_id'),
                fn (Builder $q): Builder => $q->where('organization_id', $organizationId),
            )
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
