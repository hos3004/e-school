<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Events\RoleAssigned;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

/**
 * إسناد دور لنموذج (مستخدم/ملف) عبر معرّفات الـ morph map.
 *
 * model_type يمر كاسم مستقر في الـ morph map — لا استيراد نماذج
 * من موديولات أخرى هنا أبدًا. الإسناد المكرر مرفوض.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class AssignRoleAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(
        string $roleId,
        string $modelType,
        string $modelId,
        ?string $actorId = null,
        ?string $organizationId = null,
    ): ModelHasRole {
        /** @var Role|null $role */
        $role = Role::query()
            ->when($organizationId !== null, fn (Builder $query): Builder => $query->includingGlobal($organizationId))
            ->find($roleId);

        if ($role === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.not_found',
                'accesscontrol::errors.role_not_found',
            );
        }

        $this->guardDuplicate($roleId, $modelType, $modelId);

        /** @var ModelHasRole $assignment */
        $assignment = DB::transaction(fn (): ModelHasRole => ModelHasRole::query()->create([
            'role_id' => $roleId,
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]));

        $this->events->dispatch(new RoleAssigned(
            roleId: $roleId,
            modelName: $modelType,
            modelId: $modelId,
            actorId: $actorId,
        ));

        return $assignment;
    }

    private function guardDuplicate(string $roleId, string $modelType, string $modelId): void
    {
        $exists = ModelHasRole::query()
            ->where('role_id', $roleId)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.already_assigned',
                'accesscontrol::errors.role_already_assigned',
            );
        }
    }
}
