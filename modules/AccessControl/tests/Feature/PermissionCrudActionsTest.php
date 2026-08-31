<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\AccessControl\Application\Actions\CreatePermissionAction;
use Modules\AccessControl\Application\Actions\DeletePermissionAction;
use Modules\AccessControl\Application\Actions\UpdatePermissionAction;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\PermissionCreated;
use Modules\AccessControl\Domain\Events\PermissionDeleted;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

function acCreateRoleFixture(): string
{
    return (string) Role::query()->create([
        'organization_id' => null,
        'name' => 'fixture-role-'.strtolower((string) Str::ulid()),
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ])->getKey();
}

it('creates a permission with a jsonb description', function (): void {
    Event::fake([PermissionCreated::class]);

    $permission = app(CreatePermissionAction::class)->execute(
        name: 'students.view_any',
        guard: GuardName::Web,
        module: 'students',
        description: ['ar' => 'عرض كل الطلاب', 'en' => 'View all students'],
    );

    expect($permission->description)->toBeArray()
        ->and($permission->description['ar'])->toBe('عرض كل الطلاب')
        ->and($permission->guard_name)->toBe(GuardName::Web);

    Event::assertDispatched(PermissionCreated::class);
});

it('rejects a duplicate permission name per guard', function (): void {
    $action = app(CreatePermissionAction::class);

    $action->execute('dup.action', GuardName::Web);
    $action->execute('dup.action', GuardName::Web);
})->throws(BusinessRuleViolation::class);

it('allows the same permission name across guards', function (): void {
    $action = app(CreatePermissionAction::class);

    $web = $action->execute('sessions.view_any', GuardName::Web);
    $api = $action->execute('sessions.view_any', GuardName::Api);

    expect($web->getKey())->not->toBe($api->getKey());
});

it('updates permission fields', function (): void {
    $permission = app(CreatePermissionAction::class)->execute(
        name: 'billing.export',
        guard: GuardName::Web,
        module: 'billing',
    );

    $updated = app(UpdatePermissionAction::class)->execute(
        permissionId: (string) $permission->getKey(),
        module: 'finance',
        description: ['ar' => 'تصدير الفواتير'],
    );

    expect($updated->fresh()->module)->toBe('finance')
        ->and($updated->description['ar'])->toBe('تصدير الفواتير');
});

it('rejects renaming a permission onto an existing name for the same guard', function (): void {
    $action = app(CreatePermissionAction::class);

    $action->execute('payroll.view_any', GuardName::Web);
    $other = $action->execute('payroll.export', GuardName::Web);

    try {
        app(UpdatePermissionAction::class)->execute((string) $other->getKey(), name: 'payroll.view_any');
        \PHPUnit\Framework\Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.name_taken');
    }
});

it('refuses deleting a permission that is attached to a role', function (): void {
    $permission = app(CreatePermissionAction::class)->execute('enrollments.view_any', GuardName::Web);

    DB::table('role_has_permissions')->insert([
        'role_id' => acCreateRoleFixture(),
        'permission_id' => (string) $permission->getKey(),
    ]);

    try {
        app(DeletePermissionAction::class)->execute((string) $permission->getKey());
        \PHPUnit\Framework\Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.in_use_by_roles');
    }
});

it('refuses deleting a directly-granted permission', function (): void {
    $permission = app(CreatePermissionAction::class)->execute('content.manage', GuardName::Web);

    ModelHasPermission::query()->create([
        'permission_id' => (string) $permission->getKey(),
        'model_type' => 'users',
        'model_id' => '01USER0000000000000000000',
    ]);

    try {
        app(DeletePermissionAction::class)->execute((string) $permission->getKey());
        \PHPUnit\Framework\Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.in_use_directly');
    }
});

it('deletes an unused permission and dispatches PermissionDeleted', function (): void {
    Event::fake([PermissionDeleted::class]);

    $permission = app(CreatePermissionAction::class)->execute('orphan.action', GuardName::Web);

    app(DeletePermissionAction::class)->execute((string) $permission->getKey());

    expect(Permission::query()->whereKey($permission->getKey())->exists())->toBeFalse();

    Event::assertDispatched(PermissionDeleted::class);
});
