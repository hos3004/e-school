<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\PermissionCreated;
use Modules\AccessControl\Domain\Models\Permission;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء صلاحية جديدة.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class CreatePermissionAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, string>|null  $description
     */
    public function execute(
        string $name,
        GuardName $guard,
        ?string $module = null,
        ?array $description = null,
        ?string $actorId = null,
    ): Permission {
        $this->guard($name, $guard);

        /** @var Permission $permission */
        $permission = DB::transaction(fn (): Permission => Permission::query()->create([
            'name' => $name,
            'guard_name' => $guard,
            'module' => $module,
            'description' => $description,
        ]));

        $this->events->dispatch(new PermissionCreated(
            permissionId: (string) $permission->getKey(),
            name: $permission->name,
            guardName: $permission->guard_name->value,
            actorId: $actorId,
        ));

        return $permission;
    }

    private function guard(string $name, GuardName $guard): void
    {
        $exists = Permission::query()
            ->where('name', $name)
            ->where('guard_name', $guard->value)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'accesscontrol.permission.name_taken',
                'accesscontrol::errors.permission_name_taken',
                ['name' => $name],
            );
        }
    }
}
