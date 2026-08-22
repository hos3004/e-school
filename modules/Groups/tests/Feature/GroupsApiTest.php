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
    Gate::define('groups.view_any', fn ($user) => true);
    Gate::define('groups.create', fn ($user) => true);
    Gate::define('groups.update_any', fn ($user) => true);
    Gate::define('groups.archive_any', fn ($user) => true);
    Gate::define('groups.activate', fn ($user) => true);
    Gate::define('groups.complete', fn ($user) => true);
    Gate::define('groups.enroll_student', fn ($user) => true);
    Gate::define('groups.withdraw_student', fn ($user) => true);

    $this->actor = User::factory()->create();
});

function groupPayload(array $overrides = []): array
{
    return array_merge([
        'organization_id' => GroupMembershipFactory::ensureOrganization(),
        'code' => 'GRP-API-'.str()->upper(str()->random(6)),
        'name' => ['ar' => 'مجموعة تجريبية', 'en' => 'Demo Group'],
        'capacity' => 12,
        'timezone' => 'UTC',
        'starts_on' => '2026-03-01',
        'ends_on' => null,
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
    Group::factory()->count(2)->create();

    $this->actingAs($this->actor)
        ->getJson('/api/groups')
        ->assertOk()
        ->assertJsonCount(2, 'data');
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
        ->patchJson('/api/groups/'.$group->getKey(), ['capacity' => 18])
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
    $group = Group::factory()->create();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/activate')
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/complete')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('enrolls and withdraws students through the API', function (): void {
    $group = app(ActivateGroupAction::class)->execute(Group::factory()->create());
    $studentId = GroupMembershipFactory::ensureStudentProfile();

    $membershipId = $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/students', ['student_profile_id' => $studentId])
        ->assertCreated()
        ->assertJsonPath('data.student_profile_id', $studentId)
        ->json('data.id');

    $this->actingAs($this->actor)
        ->postJson('/api/group-memberships/'.$membershipId.'/withdraw', ['reason' => 'انتقال العائلة'])
        ->assertOk()
        ->assertJsonPath('data.status', 'left');
});
