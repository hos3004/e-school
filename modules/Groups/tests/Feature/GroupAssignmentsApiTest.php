<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Database\Factories\GroupFactory;
use Modules\Groups\Database\Factories\GroupTeacherFactory;
use Modules\Groups\Domain\Events\TeacherAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('groups.view_any', fn ($user) => true);
    Gate::define('groups.assign_teacher', fn ($user) => true);
    Gate::define('groups.unassign_teacher', fn ($user) => true);
    Gate::define('groups.attach_program', fn ($user) => true);
    Gate::define('groups.detach_program', fn ($user) => true);

    $this->actor = User::factory()->create();
});

it('assigns a teacher through the API and unassigns later', function () {
    Event::fake([TeacherAssignedToGroup::class]);

    $group = app(ActivateGroupAction::class)->execute(Group::factory()->create());

    $assignmentId = $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/teachers', [
            'staff_profile_id' => GroupTeacherFactory::ensureStaffProfile(),
            'role' => 'lead',
            'assigned_from' => '2026-03-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'lead')
        ->json('data.id');

    Event::assertDispatched(TeacherAssignedToGroup::class);

    $this->actingAs($this->actor)
        ->deleteJson('/api/group-teachers/'.$assignmentId)
        ->assertOk()
        ->assertJsonPath('data.role', 'lead');
});

it('attaches and detaches programs through the API', function () {
    $group = Group::factory()->create();
    $programId = GroupFactory::ensureProgram();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', ['program_id' => $programId])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $group->getKey());

    $linkId = \Modules\Groups\Domain\Models\GroupProgram::query()
        ->forGroup((string) $group->getKey())
        ->firstOrFail()
        ->getKey();

    $this->actingAs($this->actor)
        ->deleteJson('/api/group-programs/'.$linkId)
        ->assertNoContent();
});

it('rejects duplicate program attachment through the API', function () {
    $group = Group::factory()->create();
    $programId = GroupFactory::ensureProgram();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', ['program_id' => $programId])
        ->assertOk();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', ['program_id' => $programId])
        ->assertUnprocessable();
});
