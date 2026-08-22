<?php

declare(strict_types=1);

use Modules\AccessControl\Domain\Events\ModelPermissionGranted;
use Modules\AccessControl\Domain\Events\PermissionCreated;
use Modules\AccessControl\Domain\Events\RoleAssigned;
use Modules\AccessControl\Domain\Events\RoleCreated;
use Modules\AccessControl\Domain\Events\RolePermissionsSynced;
use Modules\AccessControl\Domain\Events\RoleRevoked;

it('describes role creation in the past tense with primitive payload', function (): void {
    $event = new RoleCreated(
        roleId: '01ROLE0000000000000000000',
        organizationId: null,
        name: 'school-admin',
        guardName: 'web',
        isSystem: true,
        actorId: '01ACTOR0000000000000000000',
    );

    expect($event->name())->toBe('accesscontrol.role_created')
        ->and($event->module())->toBe('AccessControl')
        ->and($event->payload())->toBe([
            'role_id' => '01ROLE0000000000000000000',
            'organization_id' => null,
            'name' => 'school-admin',
            'guard_name' => 'web',
            'is_system' => true,
        ])
        ->and($event->actorId)->toBe('01ACTOR0000000000000000000');
});

it('carves assignment events around model type and id only', function (): void {
    $assigned = new RoleAssigned('01ROLE0000000000000000000', 'users', '01USER0000000000000000000');
    $revoked = new RoleRevoked('01ROLE0000000000000000000', 'users', '01USER0000000000000000000');

    expect($assigned->name())->toBe('accesscontrol.role_assigned')
        ->and($assigned->payload())->toBe([
            'role_id' => '01ROLE0000000000000000000',
            'model_type' => 'users',
            'model_id' => '01USER0000000000000000000',
        ])
        ->and($revoked->name())->toBe('accesscontrol.role_revoked');
});

it('summarizes permission sync by counts and keeps ids out of counts', function (): void {
    $event = new RolePermissionsSynced(
        roleId: '01ROLE0000000000000000000',
        permissionIds: ['01P1', '01P2', '01P3'],
        attached: ['01P2'],
        detached: ['01P9'],
    );

    expect($event->name())->toBe('accesscontrol.role_permissions_synced')
        ->and($event->payload()['attached_count'])->toBe(1)
        ->and($event->payload()['detached_count'])->toBe(1);
});

it('keeps permission events primitive', function (): void {
    $created = new PermissionCreated('01PERM0000000000000000000', 'students.view_any', 'web');
    $granted = new ModelPermissionGranted('01PERM0000000000000000000', 'users', '01USER0000000000000000000');

    expect($created->name())->toBe('accesscontrol.permission_created')
        ->and($created->payload()['guard_name'])->toBe('web')
        ->and($granted->module())->toBe('AccessControl')
        ->and($granted->payload())->toBe([
            'permission_id' => '01PERM0000000000000000000',
            'model_type' => 'users',
            'model_id' => '01USER0000000000000000000',
        ]);
});
