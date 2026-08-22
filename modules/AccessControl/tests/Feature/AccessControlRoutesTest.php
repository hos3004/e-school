<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Tests\Support\ApiUser;

const AC_ACTOR = '01ACTOR0000000000000000000';

beforeEach(function (): void {
    // الصلاحيات في التشغيل الفعلي تُقيّم عبر Gate من مصفوفة الأدوار؛
    // في الاختبار نمنح القدرة المطلوبة مباشرة بلا أي فحص أدوار.
    Gate::after(fn (): bool => true);
});

function acCreateRoleViaApi(string $name): string
{
    /** @var TestResponse $response */
    $response = test()->actingAs(new ApiUser(AC_ACTOR))
        ->postJson('/api/access-control/roles', ['name' => $name]);

    return (string) $response->json('data.id');
}

it('lists roles', function (): void {
    Role::query()->create([
        'organization_id' => null, 'name' => 'listable', 'guard_name' => GuardName::Web, 'is_system' => false,
    ]);

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->getJson('/api/access-control/roles')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'listable');
});

it('stores a role through the api', function (): void {
    $this->actingAs(new ApiUser(AC_ACTOR))
        ->postJson('/api/access-control/roles', [
            'name' => 'api-created',
            'guard_name' => 'web',
            'organization_id' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'api-created')
        ->assertJsonPath('data.is_system', false);

    expect(Role::query()->where('name', 'api-created')->exists())->toBeTrue();
});

it('validates the store role payload', function (): void {
    $this->actingAs(new ApiUser(AC_ACTOR))
        ->postJson('/api/access-control/roles', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a role through the api', function (): void {
    $roleId = acCreateRoleViaApi('rename-me');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->putJson("/api/access-control/roles/{$roleId}", ['name' => 'renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'renamed');
});

it('deletes a role through the api', function (): void {
    $roleId = acCreateRoleViaApi('delete-me');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->deleteJson("/api/access-control/roles/{$roleId}")
        ->assertNoContent();

    expect(Role::query()->whereKey($roleId)->exists())->toBeFalse();
});

it('syncs role permissions through the api', function (): void {
    Permission::query()->create(['name' => 'api.synced.view', 'guard_name' => GuardName::Web, 'module' => 'fixture']);
    Permission::query()->create(['name' => 'api.synced.edit', 'guard_name' => GuardName::Web, 'module' => 'fixture']);

    $roleId = acCreateRoleViaApi('synced-role');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->putJson("/api/access-control/roles/{$roleId}/permissions", [
            'permissions' => ['api.synced.view', 'api.synced.edit'],
        ])
        ->assertOk()
        ->assertJsonPath('data.role_id', $roleId);

    $names = collect($this->getJson('/api/access-control/roles')->json('data'))
        ->firstWhere('id', $roleId);

    expect($names)->not->toBeNull();
});

it('rejects syncing permissions on a system role over http with 422', function (): void {
    Role::query()->create([
        'organization_id' => null, 'name' => 'http-locked', 'guard_name' => GuardName::Web, 'is_system' => true,
    ]);
    $roleId = (string) Role::query()->where('name', 'http-locked')->value('id');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->putJson("/api/access-control/roles/{$roleId}/permissions", ['permissions' => []])
        ->assertUnprocessable();
});

it('lists permissions', function (): void {
    Permission::query()->create(['name' => 'aaa.first', 'guard_name' => GuardName::Web, 'module' => 'fixture']);

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->getJson('/api/access-control/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'aaa.first');
});

it('stores and updates and deletes a permission through the api', function (): void {
    $permissionId = (string) $this->actingAs(new ApiUser(AC_ACTOR))
        ->postJson('/api/access-control/permissions', [
            'name' => 'lifecycle.action',
            'guard_name' => 'web',
            'module' => 'fixture',
            'description' => ['ar' => 'وصف'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.module', 'fixture')
        ->json('data.id');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->putJson("/api/access-control/permissions/{$permissionId}", ['module' => 'moved'])
        ->assertOk()
        ->assertJsonPath('data.module', 'moved');

    $this->actingAs(new ApiUser(AC_ACTOR))
        ->deleteJson("/api/access-control/permissions/{$permissionId}")
        ->assertNoContent();

    expect(Permission::query()->whereKey($permissionId)->exists())->toBeFalse();
});

it('assigns then revokes a role over http', function (): void {
    $roleId = (string) Role::query()->create([
        'organization_id' => null, 'name' => 'http-assignee', 'guard_name' => GuardName::Web, 'is_system' => false,
    ])->getKey();

    $payload = [
        'role_id' => $roleId,
        'model_type' => 'users',
        'model_id' => '01USERHTTP0000000000000000',
    ];

    $this->actingAs(new ApiUser(AC_ACTOR))->postJson('/api/access-control/assignments/roles', $payload)
        ->assertCreated();

    $this->actingAs(new ApiUser(AC_ACTOR))->deleteJson('/api/access-control/assignments/roles', $payload)
        ->assertNoContent();
});

it('grants then revokes a direct permission over http', function (): void {
    Permission::query()->create(['name' => 'http.direct.grant', 'guard_name' => GuardName::Web, 'module' => 'fixture']);

    $payload = [
        'permission' => 'http.direct.grant',
        'model_type' => 'users',
        'model_id' => '01USERHTTP0000000000000000',
    ];

    $this->actingAs(new ApiUser(AC_ACTOR))->postJson('/api/access-control/assignments/permissions', $payload)
        ->assertCreated();

    $this->actingAs(new ApiUser(AC_ACTOR))->deleteJson('/api/access-control/assignments/permissions', $payload)
        ->assertNoContent();
});

it('requires authentication on every access control route', function (): void {
    $this->getJson('/api/access-control/roles')->assertUnauthorized();
    $this->postJson('/api/access-control/roles', ['name' => 'x'])->assertUnauthorized();
    $this->getJson('/api/access-control/permissions')->assertUnauthorized();
});
