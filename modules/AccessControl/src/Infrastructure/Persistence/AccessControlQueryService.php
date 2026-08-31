<?php

declare(strict_types=1);

namespace Modules\AccessControl\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\ValueObjects\RoleData;

/**
 * تنفيذ قراءة فقط فوق جداول هذا الموديول — يُرجع DTOs حصرًا.
 */
final class AccessControlQueryService implements AccessControlQuerier
{
    /** @var array<string, array<string, true>> */
    private array $directPermissions = [];

    /** @var array<string, array<string, true>> */
    private array $effectivePermissions = [];

    public function rolesAvailableToOrganization(string $organizationId): array
    {
        return DB::table('roles')
            ->where(function (Builder $query) use ($organizationId): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            })
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'guard_name', 'is_system'])
            ->map(static fn (object $row): RoleData => new RoleData(
                id: (string) $row->id,
                organizationId: $row->organization_id !== null ? (string) $row->organization_id : null,
                name: (string) $row->name,
                guardName: (string) $row->guard_name,
                isSystem: (bool) $row->is_system,
            ))
            ->all();
    }

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
        $key = "{$modelType}:{$modelId}";

        if (!array_key_exists($key, $this->directPermissions)) {
            $names = DB::table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('model_has_permissions.model_type', $modelType)
                ->where('model_has_permissions.model_id', $modelId)
                ->pluck('permissions.name')
                ->filter(static fn (mixed $name): bool => is_string($name))
                ->all();

            $this->directPermissions[$key] = array_fill_keys($names, true);
        }

        return isset($this->directPermissions[$key][$permissionName]);
    }

    public function modelHasPermission(
        string $modelType,
        string $modelId,
        string $permissionName,
        string $guardName,
    ): bool {
        $key = "{$modelType}:{$modelId}:{$guardName}";

        if (!array_key_exists($key, $this->effectivePermissions)) {
            $names = DB::table('permissions')
                ->where('permissions.guard_name', $guardName)
                ->where(function (Builder $permissions) use ($modelType, $modelId, $guardName): void {
                    $permissions
                        ->whereExists(function (Builder $direct) use ($modelType, $modelId): void {
                            $direct
                                ->selectRaw('1')
                                ->from('model_has_permissions')
                                ->whereColumn('model_has_permissions.permission_id', 'permissions.id')
                                ->where('model_has_permissions.model_type', $modelType)
                                ->where('model_has_permissions.model_id', $modelId);
                        })
                        ->orWhereExists(function (Builder $throughRole) use ($modelType, $modelId, $guardName): void {
                            $throughRole
                                ->selectRaw('1')
                                ->from('role_has_permissions')
                                ->join('model_has_roles', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
                                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                ->whereColumn('role_has_permissions.permission_id', 'permissions.id')
                                ->where('model_has_roles.model_type', $modelType)
                                ->where('model_has_roles.model_id', $modelId)
                                ->where('roles.guard_name', $guardName);
                        });
                })
                ->pluck('permissions.name')
                ->filter(static fn (mixed $name): bool => is_string($name))
                ->all();

            $this->effectivePermissions[$key] = array_fill_keys($names, true);
        }

        return isset($this->effectivePermissions[$key][$permissionName]);
    }
}
