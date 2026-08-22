<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\ModelPermissionRevoked;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
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
    ) {}

    public function execute(
        string $permissionName,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
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

        DB::transaction(function () use ($grant): void {
            $grant->delete();
        });

        $this->events->dispatch(new ModelPermissionRevoked(
            permissionId: (string) $grant->permission_id,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));
    }
}
