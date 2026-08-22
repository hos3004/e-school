<?php

declare(strict_types=1);

namespace Modules\AccessControl\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Application\Queries\RoleData;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;

/**
 * تنفيذ قراءة فقط فوق جداول هذا الموديول — يُرجع DTOs حصرًا.
 */
final readonly class AccessControlQueryService implements AccessControlQuerier
{
    public function permissionNamesForRole(string $roleId): array
    {
        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    public function rolesForModel(string $modelType, string $modelId): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $modelType)
            ->where('model_has_roles.model_id', $modelId)
            ->orderBy('roles.name')
            ->get([
                'roles.id',
                'roles.organization_id',
                'roles.name',
                'roles.guard_name',
                'roles.is_system',
            ])
            ->map(static fn (object $row): RoleData => new RoleData(
                id: (string) $row->id,
                organizationId: $row->organization_id !== null ? (string) $row->organization_id : null,
                name: (string) $row->name,
                guardName: (string) $row->guard_name,
                isSystem: (bool) $row->is_system,
            ))
            ->all();
    }

    public function modelHasDirectPermission(string $modelType, string $modelId, string $permissionName): bool
    {
        return DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', $modelType)
            ->where('model_has_permissions.model_id', $modelId)
            ->where('permissions.name', $permissionName)
            ->exists();
    }
}
