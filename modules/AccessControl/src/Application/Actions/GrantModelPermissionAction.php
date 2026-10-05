<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\ModelPermissionGranted;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;

/**
 * منح صلاحية مباشرة لنموذج دون وسيط دور — للاستثناءات المحدودة فقط.
 *
 * المنح المكرر مرفوض، والصلاحية غير الموجودة مرفوضة.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class GrantModelPermissionAction
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
    ): ModelHasPermission {
        /** @var Permission|null $permission */
        $permission = Permission::query()
            ->where('name', $permissionName)
            ->first();

        if ($permission === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.not_found',
                'accesscontrol::errors.permission_not_found',
            );
        }

        $this->guardDuplicate((string) $permission->getKey(), $modelType, $modelId);

        $permissionId = (string) $permission->getKey();

        /** @var ModelHasPermission $grant */
        $grant = DB::transaction(function () use ($permissionId, $permissionName, $modelType, $modelId, $actorId, $organizationId, $reason): ModelHasPermission {
            /** @var ModelHasPermission $created */
            $created = ModelHasPermission::query()->create([
                'permission_id' => $permissionId,
                'model_type' => $modelType,
                'model_id' => $modelId,
            ]);

            if ($actorId !== null && $organizationId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'accesscontrol.permission_granted_directly',
                    auditableType: $modelType,
                    auditableId: $modelId,
                    oldValues: null,
                    newValues: [
                        'permission_id' => $permissionId,
                        'permission_name' => $permissionName,
                    ],
                    reason: $reason,
                );
            }

            return $created;
        });

        $this->events->dispatch(new ModelPermissionGranted(
            permissionId: $permissionId,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));

        return $grant;
    }

    private function guardDuplicate(string $permissionId, string $modelType, string $modelId): void
    {
        $exists = ModelHasPermission::query()
            ->where('permission_id', $permissionId)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.already_granted',
                'accesscontrol::errors.permission_already_granted',
            );
        }
    }
}
