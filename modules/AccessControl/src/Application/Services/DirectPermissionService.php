<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Services;

use Modules\AccessControl\Application\Actions\GrantModelPermissionAction;
use Modules\AccessControl\Application\Actions\RevokeModelPermissionAction;
use Modules\AccessControl\Domain\Contracts\DirectPermissionGateway;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Shared\Support\BusinessRuleViolation;

final readonly class DirectPermissionService implements DirectPermissionGateway
{
    public function __construct(
        private GrantModelPermissionAction $grant,
        private RevokeModelPermissionAction $revoke,
    ) {}

    public function grantIfMissing(
        string $permissionName,
        string $modelType,
        string $modelId,
        string $organizationId,
        string $actorId,
        string $reason,
    ): bool {
        $this->guardConfigured($permissionName);

        if ($this->isGranted($permissionName, $modelType, $modelId)) {
            return false;
        }

        $this->grant->execute(
            permissionName: $permissionName,
            modelType: $modelType,
            modelId: $modelId,
            actorId: $actorId,
            organizationId: $organizationId,
            reason: $reason,
        );

        return true;
    }

    public function revokeIfPresent(
        string $permissionName,
        string $modelType,
        string $modelId,
        string $organizationId,
        string $actorId,
        string $reason,
    ): bool {
        $this->guardConfigured($permissionName);

        if (!$this->isGranted($permissionName, $modelType, $modelId)) {
            return false;
        }

        $this->revoke->execute(
            permissionName: $permissionName,
            modelType: $modelType,
            modelId: $modelId,
            actorId: $actorId,
            organizationId: $organizationId,
            reason: $reason,
        );

        return true;
    }

    /**
     * هذه البوابة مخصصة للصلاحيات الاختيارية المعلنة فقط. بدون هذا الحارس
     * تتحول الشاشة إلى منح أي صلاحية في النظام مباشرة بلا دور.
     */
    private function guardConfigured(string $permissionName): void
    {
        $allowed = (array) config('accesscontrol.optional_direct_permissions', []);

        if (!in_array($permissionName, $allowed, true)) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_optional',
                'accesscontrol::errors.permission_not_optional',
            );
        }
    }

    private function isGranted(string $permissionName, string $modelType, string $modelId): bool
    {
        $permissionId = Permission::query()
            ->where('name', $permissionName)
            ->value('id');

        if (!is_string($permissionId) || $permissionId === '') {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_found',
                'accesscontrol::errors.permission_not_found',
            );
        }

        return ModelHasPermission::query()
            ->where('permission_id', $permissionId)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->exists();
    }
}
