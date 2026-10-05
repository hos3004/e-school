<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleUpdated;
use Modules\AccessControl\Domain\Models\Role;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $roleId,
        ?string $name = null,
        ?string $organizationId = null,
        ?string $actorId = null,
        ?string $scopeOrganizationId = null,
        ?string $reason = null,
    ): Role {
        /** @var Role|null $role */
        $role = Role::query()
            ->when($scopeOrganizationId !== null, fn (Builder $query): Builder => $query->forOrganization($scopeOrganizationId))
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

        $this->guardDuplicateName($role, $name);

        $changes = [];

        /** @var Role $role */
        $role = DB::transaction(function () use ($role, $name, $organizationId, $actorId, $scopeOrganizationId, $reason, &$changes): Role {
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

                if ($actorId !== null && $scopeOrganizationId !== null && $reason !== null && trim($reason) !== '') {
                    $this->audit->record(
                        organizationId: $scopeOrganizationId,
                        actorId: $actorId,
                        actorType: 'user',
                        action: 'accesscontrol.role_updated',
                        auditableType: 'roles',
                        auditableId: (string) $role->getKey(),
                        oldValues: array_map(static fn (array $change): mixed => $change['from'], $changes),
                        newValues: array_map(static fn (array $change): mixed => $change['to'], $changes),
                        reason: $reason,
                    );
                }
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
