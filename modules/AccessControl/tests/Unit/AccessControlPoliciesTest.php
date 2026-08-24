<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\AccessControl\Application\Policies\PermissionPolicy;
use Modules\AccessControl\Application\Policies\RolePolicy;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;

final class AccessControlPolicyActor extends Authenticatable
{
    /** @param list<string> $abilities */
    public function __construct(
        string $organizationId = '',
        private readonly array $abilities = [],
    ) {
        parent::__construct();
        $this->forceFill(['organization_id' => $organizationId]);
    }

    public function can($abilities, $arguments = []): bool
    {
        foreach ((array) $abilities as $ability) {
            if (in_array((string) $ability, $this->abilities, true)) {
                return true;
            }
        }

        return false;
    }
}

function acPolicyActor(array $abilities = [], string $organizationId = '01ORGAAAAAAAAAAAAAAAAAAAAA'): AccessControlPolicyActor
{
    return new AccessControlPolicyActor($organizationId, $abilities);
}

function acPolicyRole(?string $organizationId, bool $system = false): Role
{
    return Role::make()->forceFill([
        'organization_id' => $organizationId,
        'is_system' => $system,
        'name' => $system ? 'system-role' : 'custom-role',
    ]);
}

it('locks system and global roles from tenant mutation', function (): void {
    $actor = acPolicyActor([
        'accesscontrol.roles.update',
        'accesscontrol.roles.delete',
        'accesscontrol.roles.sync_permissions',
    ]);
    $policy = new RolePolicy;

    expect($policy->update($actor, acPolicyRole('01ORGAAAAAAAAAAAAAAAAAAAAA', true)))->toBeFalse()
        ->and($policy->delete($actor, acPolicyRole(null)))->toBeFalse()
        ->and($policy->syncPermissions($actor, acPolicyRole(null)))->toBeFalse();
});

it('allows permitted role mutation only inside the actor tenant', function (): void {
    $actor = acPolicyActor([
        'accesscontrol.roles.update',
        'accesscontrol.roles.delete',
        'accesscontrol.roles.sync_permissions',
        'accesscontrol.assignments.assign_role',
        'accesscontrol.assignments.revoke_role',
    ]);
    $own = acPolicyRole('01ORGAAAAAAAAAAAAAAAAAAAAA');
    $foreign = acPolicyRole('01ORGBBBBBBBBBBBBBBBBBBBBB');
    $global = acPolicyRole(null, true);
    $policy = new RolePolicy;

    expect($policy->update($actor, $own))->toBeTrue()
        ->and($policy->delete($actor, $own))->toBeTrue()
        ->and($policy->syncPermissions($actor, $own))->toBeTrue()
        ->and($policy->assign($actor, $own))->toBeTrue()
        ->and($policy->revoke($actor, $own))->toBeTrue()
        ->and($policy->assign($actor, $global))->toBeTrue()
        ->and($policy->revoke($actor, $global))->toBeTrue()
        ->and($policy->update($actor, $foreign))->toBeFalse()
        ->and($policy->assign($actor, $foreign))->toBeFalse();
});

it('allows global permission definitions to be viewed but never mutated over tenant policy', function (): void {
    $actor = acPolicyActor([
        'accesscontrol.permissions.view_any',
        'accesscontrol.permissions.grant_direct',
    ]);
    $permission = Permission::make(['name' => 'student.view']);
    $policy = new PermissionPolicy;

    expect($policy->viewAny($actor))->toBeTrue()
        ->and($policy->view($actor, $permission))->toBeTrue()
        ->and($policy->grant($actor))->toBeTrue()
        ->and($policy->create($actor))->toBeFalse()
        ->and($policy->update($actor, $permission))->toBeFalse()
        ->and($policy->delete($actor, $permission))->toBeFalse();
});
