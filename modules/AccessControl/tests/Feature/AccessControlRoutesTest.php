<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\AccessControl\Tests\Support\AccessControlPestContext;
use Modules\Identity\Domain\Models\User;

function acCreateOrganization(string $slug): string
{
    $id = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => $slug, 'en' => $slug], JSON_THROW_ON_ERROR),
        'slug' => $slug.'-'.strtolower($id),
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);

    return $id;
}

function acAssignSeededRole(User $user, string $roleName): Role
{
    $role = Role::query()
        ->includingGlobal($user->organization_id)
        ->where('name', $roleName)
        ->firstOrFail();

    ModelHasRole::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
    ]);

    return $role;
}

beforeEach(function (): void {
    /** @var AccessControlPestContext $this */
    $this->organizationId = acCreateOrganization('access-a');
    (new AccessControlSeeder)->run();
    $this->otherOrganizationId = acCreateOrganization('access-b');
    $this->actor = User::factory()->inOrganization($this->organizationId)->create();
    acAssignSeededRole($this->actor, 'platform_admin');
    app(PermissionGateRegistrar::class)->register();
});

it('uses real production permissions and lists only tenant plus global roles', function (): void {
    /** @var AccessControlPestContext $this */
    Role::query()->create([
        'organization_id' => $this->otherOrganizationId,
        'name' => 'other-tenant-role',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);
    Role::query()->create([
        'organization_id' => null,
        'name' => 'global-read-only-role',
        'guard_name' => GuardName::Web,
        'is_system' => true,
    ]);

    $data = $this->actingAs($this->actor)
        ->getJson('/api/access-control/roles')
        ->assertOk()
        ->json('data');
    $this->assertIsArray($data);
    /** @var list<array{name: string}> $data */
    $names = collect($data)
        ->pluck('name');

    expect($names)->toContain('platform_admin', 'global-read-only-role');
    expect($names->contains('other-tenant-role'))->toBeFalse();
});

it('forces new roles into the actor tenant and rejects a supplied tenant', function (): void {
    /** @var AccessControlPestContext $this */
    $response = $this->actingAs($this->actor)->postJson('/api/access-control/roles', [
        'name' => 'tenant-custom',
        'guard_name' => 'web',
        'reason' => 'new academic operations role approved',
    ])->assertCreated();

    expect(Role::query()->findOrFail($response->json('data.id'))->organization_id)
        ->toBe($this->organizationId);

    $this->actingAs($this->actor)->postJson('/api/access-control/roles', [
        'name' => 'spoofed-custom',
        'organization_id' => $this->otherOrganizationId,
        'reason' => 'cross-tenant attempt',
    ])->assertUnprocessable()->assertJsonValidationErrors(['organization_id']);
});

it('returns not found for cross-tenant role update delete and sync', function (): void {
    /** @var AccessControlPestContext $this */
    $role = Role::query()->create([
        'organization_id' => $this->otherOrganizationId,
        'name' => 'foreign-role',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);

    $this->actingAs($this->actor)
        ->putJson("/api/access-control/roles/{$role->id}", [
            'name' => 'hijacked',
            'reason' => 'cross-tenant attempt',
        ])
        ->assertNotFound();
    $this->actingAs($this->actor)
        ->deleteJson("/api/access-control/roles/{$role->id}", ['reason' => 'cross-tenant attempt'])
        ->assertNotFound();
    $this->actingAs($this->actor)
        ->putJson("/api/access-control/roles/{$role->id}/permissions", [
            'permissions' => [],
            'reason' => 'cross-tenant attempt',
        ])
        ->assertNotFound();
});

it('requires a written reason for every access mutation', function (): void {
    /** @var AccessControlPestContext $this */
    $target = User::factory()->inOrganization($this->organizationId)->create();

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/roles', ['name' => 'missing-reason'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/permissions', [
            'permission' => 'admin.panel.access',
            'model_id' => $target->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('assigns and revokes roles only for users in the actor tenant', function (): void {
    /** @var AccessControlPestContext $this */
    $target = User::factory()->inOrganization($this->organizationId)->create();
    $foreign = User::factory()->inOrganization($this->otherOrganizationId)->create();
    $role = Role::query()->create([
        'organization_id' => $this->organizationId,
        'name' => 'custom-assignment',
        'guard_name' => GuardName::Web,
        'is_system' => false,
    ]);
    $payload = [
        'role_id' => $role->id,
        'model_id' => $target->id,
        'model_type' => 'attacker-controlled',
        'reason' => 'approved assignment request',
    ];

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/roles', $payload)
        ->assertCreated();

    expect(ModelHasRole::query()
        ->where('role_id', $role->id)
        ->where('model_type', $target->getMorphClass())
        ->where('model_id', $target->id)
        ->exists())->toBeTrue();

    $this->actingAs($this->actor)
        ->deleteJson('/api/access-control/assignments/roles', $payload)
        ->assertNoContent();

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/roles', [
            'role_id' => $role->id,
            'model_id' => $foreign->id,
            'reason' => 'cross-tenant attempt',
        ])
        ->assertNotFound();
});

it('assigns and revokes a global system role only to a same-tenant user', function (): void {
    /** @var AccessControlPestContext $this */
    $target = User::factory()->inOrganization($this->organizationId)->create();
    $foreign = User::factory()->inOrganization($this->otherOrganizationId)->create();
    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'global-system-assignment',
        'guard_name' => GuardName::Web,
        'is_system' => true,
    ]);

    $payload = [
        'role_id' => $role->id,
        'model_id' => $target->id,
        'reason' => 'approved global role assignment',
    ];
    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/roles', $payload)
        ->assertCreated();
    $this->actingAs($this->actor)
        ->deleteJson('/api/access-control/assignments/roles', $payload)
        ->assertNoContent();

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/roles', [
            'role_id' => $role->id,
            'model_id' => $foreign->id,
            'reason' => 'cross-tenant attempt',
        ])->assertNotFound();
});

it('grants then revokes a direct permission only for a same-tenant account', function (): void {
    /** @var AccessControlPestContext $this */
    $target = User::factory()->inOrganization($this->organizationId)->create();
    $foreign = User::factory()->inOrganization($this->otherOrganizationId)->create();
    $payload = [
        'permission' => 'admin.panel.access',
        'model_id' => $target->id,
        'reason' => 'temporary operational access request AC-2026-108',
    ];

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/permissions', $payload)
        ->assertCreated();

    expect(ModelHasPermission::query()
        ->where('model_type', $target->getMorphClass())
        ->where('model_id', $target->id)
        ->exists())->toBeTrue();

    $this->actingAs($this->actor)
        ->deleteJson('/api/access-control/assignments/permissions', $payload)
        ->assertNoContent();

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/assignments/permissions', [
            'permission' => 'admin.panel.access',
            'model_id' => $foreign->id,
            'reason' => 'cross-tenant attempt',
        ])->assertNotFound();
});

it('keeps the global permission catalog read-only over tenant HTTP', function (): void {
    /** @var AccessControlPestContext $this */
    $this->actingAs($this->actor)
        ->getJson('/api/access-control/permissions')
        ->assertOk();

    $this->actingAs($this->actor)
        ->postJson('/api/access-control/permissions', ['name' => 'unsafe.global.mutation'])
        ->assertMethodNotAllowed();
});

it('denies access-control management without a real seeded capability', function (): void {
    /** @var AccessControlPestContext $this */
    $student = User::factory()->inOrganization($this->organizationId)->create();
    acAssignSeededRole($student, 'student');

    $this->actingAs($student)->getJson('/api/access-control/roles')->assertForbidden();
    $this->actingAs($student)->getJson('/api/access-control/permissions')->assertForbidden();
});

it('requires authentication on every access control route', function (): void {
    /** @var AccessControlPestContext $this */
    $this->getJson('/api/access-control/roles')->assertUnauthorized();
    $this->postJson('/api/access-control/roles', ['name' => 'x'])->assertUnauthorized();
    $this->getJson('/api/access-control/permissions')->assertUnauthorized();
});
