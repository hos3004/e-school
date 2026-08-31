<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Database\Factories\GroupMembershipFactory;
use Modules\Groups\Domain\Events\GroupCreated;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('group.view', fn ($user) => true);
    Gate::define('group.manage', fn ($user) => true);
    Gate::define('enrollment.create', fn ($user) => true);

    $this->actor = User::factory()->create();
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function groupPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'GRP-API-'.str()->upper(str()->random(6)),
        'name' => ['ar' => 'مجموعة تجريبية', 'en' => 'Demo Group'],
        'capacity' => 12,
        'timezone' => 'UTC',
        'starts_on' => '2026-03-01',
        'ends_on' => null,
        'reason' => 'فتح مجموعة للفصل الدراسي الجديد',
    ], $overrides);
}

it('creates a group through the API', function (): void {
    Event::fake([GroupCreated::class]);

    $this->actingAs($this->actor)
        ->postJson('/api/groups', groupPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'planning');

    Event::assertDispatched(GroupCreated::class);
});

it('validates the create payload', function (): void {
    $this->actingAs($this->actor)
        ->postJson('/api/groups', groupPayload(['capacity' => 0, 'code' => 'bad code!']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['capacity', 'code']);
});

it('lists groups with active member counts', function (): void {
    $groups = Group::factory()->count(2)->create();

    $ids = collect($this->actingAs($this->actor)
        ->getJson('/api/groups')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)->toContain(...$groups->modelKeys());
});

it('shows one group and hides archived ones', function (): void {
    $group = Group::factory()->create();

    $this->actingAs($this->actor)
        ->getJson('/api/groups/'.$group->getKey())
        ->assertOk()
        ->assertJsonPath('data.id', (string) $group->getKey());

    $group->delete();

    $this->actingAs($this->actor)
        ->getJson('/api/groups/'.$group->getKey())
        ->assertNotFound();
});

it('updates a group through the API without touching status', function (): void {
    $group = Group::factory()->create();

    $this->actingAs($this->actor)
        ->patchJson('/api/groups/'.$group->getKey(), [
            'capacity' => 18,
            'reason' => 'تحديث السعة حسب عدد المسجلين',
        ])
        ->assertOk()
        ->assertJsonPath('data.capacity', 18)
        ->assertJsonPath('data.status', 'planning');
});

it('archives with a reason through the API', function (): void {
    $group = Group::factory()->create();

    $this->actingAs($this->actor)
        ->deleteJson('/api/groups/'.$group->getKey(), ['reason' => 'دمج مع مجموعة أخرى'])
        ->assertNoContent();

    expect($group->refresh()->trashed())->toBeTrue();
});

it('rejects archiving without a reason', function (): void {
    $group = Group::factory()->create();

    $this->actingAs($this->actor)
        ->deleteJson('/api/groups/'.$group->getKey())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('activates then completes a group through dedicated endpoints', function (): void {
    // التفعيل يشترط معلمًا مُسندًا — راجع config('groups.activation').
    $group = Group::factory()->activatable()->create();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/activate', ['reason' => 'اكتمل تجهيز المجموعة'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/complete', ['reason' => 'اكتملت الخطة الدراسية'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('withdraws an existing student membership through the API', function (): void {
    $group = app(ActivateGroupAction::class)->execute(Group::factory()->activatable()->create());
    $studentId = GroupMembershipFactory::ensureStudentProfile();
    $membership = GroupMembershipFactory::new()->create([
        'group_id' => $group->getKey(),
        'student_profile_id' => $studentId,
    ]);

    $this->actingAs($this->actor)
        ->postJson('/api/group-memberships/'.$membership->getKey().'/withdraw', ['reason' => 'انتقال العائلة'])
        ->assertOk()
        ->assertJsonPath('data.status', 'left');
});

it('derives the organization from the actor and rejects a supplied organization', function (): void {
    $payload = groupPayload(['organization_id' => GroupMembershipFactory::ensureOrganization()]);

    $this->actingAs($this->actor)
        ->postJson('/api/groups', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['organization_id']);
});
