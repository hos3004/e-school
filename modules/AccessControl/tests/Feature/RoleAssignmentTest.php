<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AccessControl\Application\Actions\AssignRoleAction;
use Modules\AccessControl\Application\Actions\RevokeRoleAction;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\RoleAssigned;
use Modules\AccessControl\Domain\Events\RoleRevoked;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use PHPUnit\Framework\Assert;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

const AC_USER_TYPE = 'users';
const AC_USER_ID = '01USER0000000000000000000';

it('assigns a role to a model and dispatches RoleAssigned', function (): void {
    Event::fake([RoleAssigned::class]);

    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'assignable',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    app(AssignRoleAction::class)->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);

    expect(ModelHasRole::query()
        ->where('role_id', $role->getKey())
        ->where('model_type', AC_USER_TYPE)
        ->where('model_id', AC_USER_ID)
        ->exists())->toBeTrue();

    Event::assertDispatched(RoleAssigned::class, fn (RoleAssigned $e): bool => $e->modelName === AC_USER_TYPE && $e->modelId === AC_USER_ID);
});

it('rejects a duplicate assignment', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'once-only',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    $action = app(AssignRoleAction::class);
    $action->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);
    $action->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);
})->throws(BusinessRuleViolation::class);

it('rejects assigning an unknown role', function (): void {
    app(AssignRoleAction::class)->execute('01UNKNOWN00000000000000000', AC_USER_TYPE, AC_USER_ID);
})->throws(BusinessRuleViolation::class);

it('revokes an existing assignment and dispatches RoleRevoked', function (): void {
    Event::fake([RoleRevoked::class]);

    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'revokable',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    app(AssignRoleAction::class)->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);
    app(RevokeRoleAction::class)->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);

    expect(ModelHasRole::query()->where('role_id', $role->getKey())->exists())->toBeFalse();

    Event::assertDispatched(RoleRevoked::class);
});

it('refuses revoking a role that was never assigned', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'never-assigned',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    try {
        app(RevokeRoleAction::class)->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);
        Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.role.not_assigned');
    }
});

it('lists assigned roles for a model through the querier', function (): void {
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'queried-assignment',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    app(AssignRoleAction::class)->execute((string) $role->getKey(), AC_USER_TYPE, AC_USER_ID);

    $roles = app(AccessControlQuerier::class)->rolesForModel(AC_USER_TYPE, AC_USER_ID);

    expect(count($roles))->toBe(1)
        ->and($roles[0]->name)->toBe('queried-assignment')
        ->and($roles[0]->toArray()['guard_name'])->toBe('web');
});

it('audits role assignment and revocation with the written reasons', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();
    $targetId = Fixtures::userId();
    $role = Role::query()->create([
        'organization_id' => $organizationId,
        'name' => 'audited-operator',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    app(AssignRoleAction::class)->execute(
        (string) $role->getKey(),
        AC_USER_TYPE,
        $targetId,
        $actorId,
        $organizationId,
        'approved access request AC-2026-104',
    );

    app(RevokeRoleAction::class)->execute(
        (string) $role->getKey(),
        AC_USER_TYPE,
        $targetId,
        $actorId,
        $organizationId,
        'access request expired at the end of assignment',
    );

    expect(DB::table('audit_log')->where([
        'action' => 'accesscontrol.role_assigned',
        'auditable_id' => $targetId,
        'reason' => 'approved access request AC-2026-104',
    ])->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where([
            'action' => 'accesscontrol.role_revoked',
            'auditable_id' => $targetId,
            'reason' => 'access request expired at the end of assignment',
        ])->exists())->toBeTrue();
});
