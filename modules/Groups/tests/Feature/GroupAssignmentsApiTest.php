<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Database\Factories\GroupFactory;
use Modules\Groups\Database\Factories\GroupTeacherFactory;
use Modules\Groups\Domain\Events\TeacherAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('group.view', fn ($user) => true);
    Gate::define('group.manage', fn ($user) => true);

    $this->actor = User::factory()->create();
});

it('assigns a teacher through the API and unassigns later', function (): void {
    Event::fake([TeacherAssignedToGroup::class]);

    $group = app(ActivateGroupAction::class)->execute(Group::factory()->activatable()->create());

    $assignmentId = $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/teachers', [
            'staff_profile_id' => GroupTeacherFactory::ensureStaffProfile(),
            'role' => 'lead',
            'assigned_from' => '2026-03-01',
            'reason' => 'اعتماد المعلم الأساسي للمجموعة',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'lead')
        ->json('data.id');

    Event::assertDispatched(TeacherAssignedToGroup::class);

    $this->actingAs($this->actor)
        ->deleteJson('/api/group-teachers/'.$assignmentId, ['reason' => 'نهاية إسناد المعلم'])
        ->assertOk()
        ->assertJsonPath('data.role', 'lead');
});

it('attaches and detaches programs through the API', function (): void {
    $group = Group::factory()->create();
    $programId = GroupFactory::ensureProgram();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', [
            'program_id' => $programId,
            'reason' => 'ربط الخطة الأكاديمية بالمجموعة',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $group->getKey());

    $linkId = GroupProgram::query()
        ->forGroup((string) $group->getKey())
        ->firstOrFail()
        ->getKey();

    $this->actingAs($this->actor)
        ->deleteJson('/api/group-programs/'.$linkId, ['reason' => 'تغيير خطة المجموعة'])
        ->assertNoContent();
});

it('rejects duplicate program attachment through the API', function (): void {
    $group = Group::factory()->create();
    $programId = GroupFactory::ensureProgram();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', [
            'program_id' => $programId,
            'reason' => 'ربط البرنامج بالمجموعة',
        ])
        ->assertOk();

    $this->actingAs($this->actor)
        ->postJson('/api/groups/'.$group->getKey().'/programs', [
            'program_id' => $programId,
            'reason' => 'محاولة ربط مكررة',
        ])
        ->assertUnprocessable();
});
