<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AccessControl\Application\Actions\CreateRoleAction;
use Modules\AccessControl\Application\Actions\DeleteRoleAction;
use Modules\AccessControl\Application\Actions\UpdateRoleAction;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\RoleCreated;
use Modules\AccessControl\Domain\Events\RoleDeleted;
use Modules\AccessControl\Domain\Events\RoleUpdated;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

it('creates a role and dispatches RoleCreated', function (): void {
    Event::fake([RoleCreated::class]);

    $role = app(CreateRoleAction::class)->execute(
        name: 'academic-coordinator',
        guard: GuardName::Web,
    );

    expect($role->exists)->toBeTrue()
        ->and($role->name)->toBe('academic-coordinator')
        ->and($role->is_system)->toBeFalse()
        ->and(strlen((string) $role->getKey()))->toBe(26);

    Event::assertDispatched(RoleCreated::class, fn (RoleCreated $e): bool => $e->roleId === (string) $role->getKey() && $e->isSystem === false);
});

it('rejects a duplicate role name within the same organization and guard', function (): void {
    $action = app(CreateRoleAction::class);

    $action->execute('duplicate-me', GuardName::Web);
    $action->execute('duplicate-me', GuardName::Web);
})->throws(BusinessRuleViolation::class);

it('allows the same role name across different guards', function (): void {
    $action = app(CreateRoleAction::class);

    $web = $action->execute('multi-guard', GuardName::Web);
    $api = $action->execute('multi-guard', GuardName::Api);

    expect($web->getKey())->not->toBe($api->getKey());
});

it('renames a non-system role and dispatches RoleUpdated', function (): void {
    Event::fake([RoleUpdated::class]);

    $role = app(CreateRoleAction::class)->execute('old-name', GuardName::Web);

    $updated = app(UpdateRoleAction::class)->execute(
        roleId: (string) $role->getKey(),
        name: 'new-name',
    );

    expect($updated->fresh()->name)->toBe('new-name');

    Event::assertDispatched(RoleUpdated::class, fn (RoleUpdated $e): bool => array_key_exists('name', $e->changed));
});

it('refuses to modify a system role', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'super-admin',
        'guard_name' => GuardName::Web,
        'is_system' => true,
    ]);

    try {
        app(UpdateRoleAction::class)->execute((string) $role->getKey(), name: 'renamed');
        self::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.role.system_locked');
    }
});

it('refuses an unknown role update', function (): void {
    app(UpdateRoleAction::class)->execute('01UNKNOWN00000000000000000');
})->throws(BusinessRuleViolation::class);

it('deletes an unused non-system role and dispatches RoleDeleted', function (): void {
    Event::fake([RoleDeleted::class]);

    $role = app(CreateRoleAction::class)->execute('doomed-role', GuardName::Web);

    app(DeleteRoleAction::class)->execute((string) $role->getKey());

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();

    Event::assertDispatched(RoleDeleted::class);
});

it('refuses deleting a system role', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'school-admin',
        'guard_name' => GuardName::Web,
        'is_system' => true,
    ]);

    try {
        app(DeleteRoleAction::class)->execute((string) $role->getKey());
        self::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.role.system_locked');
    }
});

it('audits the tenant role lifecycle with written reasons', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();

    $role = app(CreateRoleAction::class)->execute(
        name: 'audited-lifecycle',
        guard: GuardName::Web,
        organizationId: $organizationId,
        actorId: $actorId,
        reason: 'approved access-control role request',
    );

    app(UpdateRoleAction::class)->execute(
        roleId: (string) $role->getKey(),
        name: 'audited-lifecycle-updated',
        actorId: $actorId,
        scopeOrganizationId: $organizationId,
        reason: 'role naming convention correction',
    );

    app(DeleteRoleAction::class)->execute(
        roleId: (string) $role->getKey(),
        actorId: $actorId,
        organizationId: $organizationId,
        reason: 'role is no longer required',
    );

    expect(DB::table('audit_log')->where([
        'action' => 'accesscontrol.role_created',
        'auditable_id' => $role->getKey(),
        'reason' => 'approved access-control role request',
    ])->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where([
            'action' => 'accesscontrol.role_updated',
            'auditable_id' => $role->getKey(),
            'reason' => 'role naming convention correction',
        ])->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where([
            'action' => 'accesscontrol.role_deleted',
            'auditable_id' => $role->getKey(),
            'reason' => 'role is no longer required',
        ])->exists())->toBeTrue();
});
