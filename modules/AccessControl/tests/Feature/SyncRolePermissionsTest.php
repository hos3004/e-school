<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AccessControl\Application\Actions\CreatePermissionAction;
use Modules\AccessControl\Application\Actions\CreateRoleAction;
use Modules\AccessControl\Application\Actions\SyncRolePermissionsAction;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\RolePermissionsSynced;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

function acRoleWithPermissions(string $name, array $permissionNames): Role
{
    $role = app(CreateRoleAction::class)->execute($name, GuardName::Web);

    foreach ($permissionNames as $permissionName) {
        app(CreatePermissionAction::class)->execute($permissionName, GuardName::Web, 'fixture');
    }

    app(SyncRolePermissionsAction::class)->execute((string) $role->getKey(), $permissionNames);

    return $role->refresh();
}

it('syncs the full target state of role permissions', function (): void {
    Event::fake([RolePermissionsSynced::class]);

    $role = acRoleWithPermissions('reporter', ['reports.view_any', 'reports.export']);

    expect(app(AccessControlQuerier::class)->permissionNamesForRole((string) $role->getKey()))
        ->toBe(['reports.export', 'reports.view_any']);

    Event::assertDispatched(RolePermissionsSynced::class);
});

it('detaches permissions missing from the target list', function (): void {
    $action = app(SyncRolePermissionsAction::class);
    $creator = app(CreatePermissionAction::class);

    $role = app(CreateRoleAction::class)->execute('mutable-role', GuardName::Web);
    $roleId = (string) $role->getKey();

    foreach (['alpha.one', 'beta.two'] as $permissionName) {
        $creator->execute($permissionName, GuardName::Web);
    }

    $action->execute($roleId, ['alpha.one', 'beta.two']);
    $action->execute($roleId, ['beta.two']);

    $querier = app(AccessControlQuerier::class);

    expect($querier->permissionNamesForRole($roleId))->toBe(['beta.two']);
});

it('dispatches nothing when the sync is a no-op', function (): void {
    $role = acRoleWithPermissions('stable-role', ['keep.one']);
    $roleId = (string) $role->getKey();

    Event::fake([RolePermissionsSynced::class]);

    app(SyncRolePermissionsAction::class)->execute($roleId, ['keep.one']);

    Event::assertNotDispatched(RolePermissionsSynced::class);
});

it('rejects unknown permission names', function (): void {
    $role = app(CreateRoleAction::class)->execute('no-perms', GuardName::Web);

    try {
        app(SyncRolePermissionsAction::class)->execute((string) $role->getKey(), ['ghost.permission']);
        self::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.not_found');
    }
});

it('rejects permissions whose guard does not match the role guard', function (): void {
    $role = app(CreateRoleAction::class)->execute('web-only', GuardName::Web);
    app(CreatePermissionAction::class)->execute('api.only', GuardName::Api);

    try {
        app(SyncRolePermissionsAction::class)->execute((string) $role->getKey(), ['api.only']);
        self::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.guard_mismatch');
    }
});

it('refuses syncing permissions on a system role', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'locked-admin',
        'guard_name' => GuardName::Web,
        'is_system' => true,
    ]);

    try {
        app(SyncRolePermissionsAction::class)->execute((string) $role->getKey(), []);
        self::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.role.system_locked');
    }
});

it('answers cross-module queries through the querier only', function (): void {
    $role = acRoleWithPermissions('queried-role', ['query.me']);

    $querier = app(AccessControlQuerier::class);

    expect($querier->modelHasDirectPermission('users', '01USER0000000000000000000', 'query.me'))->toBeFalse()
        ->and($querier->rolesForModel('users', '01USER0000000000000000000'))->toBeArray();
});

it('audits a changed role permission set with the written reason', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();
    $role = app(CreateRoleAction::class)->execute('audited-sync', GuardName::Web, $organizationId);
    app(CreatePermissionAction::class)->execute('reports.audit', GuardName::Web);

    app(SyncRolePermissionsAction::class)->execute(
        roleId: (string) $role->getKey(),
        permissionNames: ['reports.audit'],
        actorId: $actorId,
        organizationId: $organizationId,
        reason: 'reporting responsibilities approved',
    );

    expect(DB::table('audit_log')->where([
        'action' => 'accesscontrol.role_permissions_synced',
        'auditable_id' => $role->getKey(),
        'reason' => 'reporting responsibilities approved',
    ])->exists())->toBeTrue();
});
