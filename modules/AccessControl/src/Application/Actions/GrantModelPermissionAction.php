<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\ModelPermissionGranted;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
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
    ) {}

    public function execute(
        string $permissionName,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
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

        /** @var ModelHasPermission $grant */
        $grant = DB::transaction(fn (): ModelHasPermission => ModelHasPermission::query()->create([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]));

        $this->events->dispatch(new ModelPermissionGranted(
            permissionId: (string) $permission->getKey(),
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
