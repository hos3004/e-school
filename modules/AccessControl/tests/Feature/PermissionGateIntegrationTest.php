<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Domain\Models\RoleHasPermission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\AccessControl\Tests\Support\ApiUser;

it('allows assigned database permissions and denies unassigned permissions', function (): void {
    $allowed = Permission::query()->create([
        'name' => 'session.view',
        'guard_name' => GuardName::Web,
        'module' => 'Sessions',
    ]);
    Permission::query()->create([
        'name' => 'session.cancel',
        'guard_name' => GuardName::Web,
        'module' => 'Sessions',
    ]);
    $direct = Permission::query()->create([
        'name' => 'settings.manage',
        'guard_name' => GuardName::Web,
        'module' => 'Organization',
    ]);
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'gate-fixture',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);
    $user = new ApiUser('01GATEUSER0000000000000000');

    RoleHasPermission::query()->create([
        'role_id' => (string) $role->getKey(),
        'permission_id' => (string) $allowed->getKey(),
    ]);
    ModelHasRole::query()->create([
        'role_id' => (string) $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => (string) $user->getAuthIdentifier(),
    ]);
    ModelHasPermission::query()->create([
        'permission_id' => (string) $direct->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => (string) $user->getAuthIdentifier(),
    ]);

    app(PermissionGateRegistrar::class)->register();

    expect(Gate::forUser($user)->allows('session.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('settings.manage'))->toBeTrue()
        ->and(Gate::forUser($user)->denies('session.cancel'))->toBeTrue();
});
