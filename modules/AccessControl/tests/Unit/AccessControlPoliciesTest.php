<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Application\Policies\PermissionPolicy;
use Modules\AccessControl\Application\Policies\RolePolicy;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Tests\Support\ApiUser;

function acPolicyUser(): ApiUser
{
    return new ApiUser('01ACTOR0000000000000000000');
}

function acSystemRole(): Role
{
    return Role::make()->forceFill(['is_system' => true, 'name' => 'super-admin']);
}

function acPlainRole(): Role
{
    return Role::make()->forceFill(['is_system' => false, 'name' => 'custom']);
}

it('locks system roles from update and delete at policy level', function (): void {
    Gate::define('accesscontrol.roles.update', fn (): bool => true);
    Gate::define('accesscontrol.roles.delete', fn (): bool => true);
    Gate::define('accesscontrol.roles.sync_permissions', fn (): bool => true);

    $policy = new RolePolicy;

    expect($policy->update(acPolicyUser(), acSystemRole()))->toBeFalse()
        ->and($policy->delete(acPolicyUser(), acSystemRole()))->toBeFalse()
        ->and($policy->syncPermissions(acPolicyUser(), acSystemRole()))->toBeFalse();
});

it('allows update, delete and sync on non-system roles for permitted users', function (): void {
    Gate::define('accesscontrol.roles.update', fn (): bool => true);
    Gate::define('accesscontrol.roles.delete', fn (): bool => true);
    Gate::define('accesscontrol.roles.sync_permissions', fn (): bool => true);

    $policy = new RolePolicy;

    expect($policy->update(acPolicyUser(), acPlainRole()))->toBeTrue()
        ->and($policy->delete(acPolicyUser(), acPlainRole()))->toBeTrue()
        ->and($policy->syncPermissions(acPolicyUser(), acPlainRole()))->toBeTrue();
});

it('denies everything when the user lacks the underlying ability', function (): void {
    $policy = new RolePolicy;
    $user = acPolicyUser();

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, acPlainRole()))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->assign($user))->toBeFalse()
        ->and($policy->revoke($user))->toBeFalse();
});

it('gates permission management behind permission abilities only', function (): void {
    Gate::define('accesscontrol.permissions.view_any', fn (): bool => true);
    Gate::define('accesscontrol.permissions.create', fn (): bool => false);
    Gate::define('accesscontrol.permissions.grant_direct', fn (): bool => true);

    $policy = new PermissionPolicy;
    $permission = Permission::make(['name' => 'students.view_any']);

    expect($policy->viewAny(acPolicyUser()))->toBeTrue()
        ->and($policy->view(acPolicyUser(), $permission))->toBeTrue()
        ->and($policy->create(acPolicyUser()))->toBeFalse()
        ->and($policy->grant(acPolicyUser()))->toBeTrue();
});
