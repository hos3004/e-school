<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\ModelPermissionRevoked;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;

/**
 * سحب صلاحية مباشرة من نموذج — المنح غير الموجود مرفوض.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class RevokeModelPermissionAction
{
    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $permissionName,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
        ?string $organizationId = null,
        ?string $reason = null,
    ): void {
        /** @var ModelHasPermission|null $grant */
        $grant = ModelHasPermission::query()
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', $modelType)
            ->where('model_has_permissions.model_id', $modelId)
            ->where('permissions.name', $permissionName)
            ->first(['model_has_permissions.*']);

        if ($grant === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_granted',
                'accesscontrol::errors.permission_not_granted',
            );
        }

        DB::transaction(function () use ($grant, $permissionName, $modelType, $modelId, $actorId, $organizationId, $reason): void {
            ModelHasPermission::query()
                ->where('permission_id', $grant->permission_id)
                ->where('model_type', $modelType)
                ->where('model_id', $modelId)
                ->delete();

            if ($actorId !== null && $organizationId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'accesscontrol.permission_revoked_directly',
                    auditableType: $modelType,
                    auditableId: $modelId,
                    oldValues: [
                        'permission_id' => (string) $grant->permission_id,
                        'permission_name' => $permissionName,
                    ],
                    newValues: null,
                    reason: $reason,
                );
            }
        });

        $this->events->dispatch(new ModelPermissionRevoked(
            permissionId: (string) $grant->permission_id,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));
    }
}
