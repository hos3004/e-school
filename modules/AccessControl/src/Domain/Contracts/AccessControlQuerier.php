<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Contracts;

use Modules\AccessControl\Domain\ValueObjects\RoleData;

/**
 * قراءة فقط — الواجهة العامة لموديول AccessControl لبقية الموديولات.
 *
 * بقية الموديولات تسأل هنا عن الأدوار والصلاحيات وتحصل على DTOs،
 * ولا تلمس جداول هذا الموديول أو نماذجه أبدًا.
 */
interface AccessControlQuerier
{
    /** @return list<RoleData> */
    public function rolesAvailableToOrganization(string $organizationId): array;

    /**
     * أسماء صلاحيات الدور (عبر role_has_permissions).
     *
     * @return list<string>
     */
    public function permissionNamesForRole(string $roleId): array;

    /**
     * معرّفات الأدوار المسندة لنموذج.
     *
     * @return list<RoleData>
     */
    public function rolesForModel(string $modelType, string $modelId): array;

    /**
     * هل يملك النموذج هذه الصلاحية مباشرة (دون دور)؟
     */
    public function modelHasDirectPermission(string $modelType, string $modelId, string $permissionName): bool;

    /**
     * هل يملك النموذج الصلاحية عبر دور مسند أو منحة مباشرة؟
     */
    public function modelHasPermission(
        string $modelType,
        string $modelId,
        string $permissionName,
        string $guardName,
    ): bool;
}
