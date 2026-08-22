<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleRevoked;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Shared\Support\BusinessRuleViolation;

/**
 * سحب دور من نموذج — الإسناد غير الموجود مرفوض.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class RevokeRoleAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(
        string $roleId,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
    ): void {
        /** @var ModelHasRole|null $assignment */
        $assignment = ModelHasRole::query()
            ->where('role_id', $roleId)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->first();

        if ($assignment === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.not_assigned',
                'accesscontrol::errors.role_not_assigned',
            );
        }

        DB::transaction(function () use ($roleId, $modelType, $modelId): void {
            ModelHasRole::query()
                ->where('role_id', $roleId)
                ->where('model_type', $modelType)
                ->where('model_id', $modelId)
                ->delete();
        });

        $this->events->dispatch(new RoleRevoked(
            roleId: $roleId,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));
    }
}
