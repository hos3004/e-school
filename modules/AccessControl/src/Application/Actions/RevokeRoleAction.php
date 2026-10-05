<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleRevoked;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $roleId,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
        ?string $organizationId = null,
        ?string $reason = null,
    ): void {
        $roleExists = Role::query()
            ->when($organizationId !== null, fn (Builder $query): Builder => $query->includingGlobal($organizationId))
            ->whereKey($roleId)
            ->exists();

        if (!$roleExists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.not_found',
                'accesscontrol::errors.role_not_found',
            );
        }

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

        DB::transaction(function () use ($roleId, $modelType, $modelId, $actorId, $organizationId, $reason): void {
            ModelHasRole::query()
                ->where('role_id', $roleId)
                ->where('model_type', $modelType)
                ->where('model_id', $modelId)
                ->delete();

            if ($actorId !== null && $organizationId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'accesscontrol.role_revoked',
                    auditableType: $modelType,
                    auditableId: $modelId,
                    oldValues: ['role_id' => $roleId],
                    newValues: null,
                    reason: $reason,
                );
            }
        });

        $this->events->dispatch(new RoleRevoked(
            roleId: $roleId,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));
    }
}
