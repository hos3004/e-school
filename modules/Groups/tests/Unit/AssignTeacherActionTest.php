<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Application\Actions\UnassignTeacherAction;
use Modules\Groups\Database\Factories\GroupTeacherFactory;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Events\TeacherAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function assignmentData(array $overrides = []): array
{
    return array_merge([
        'staff_profile_id' => GroupTeacherFactory::ensureStaffProfile(),
        'course_id' => null,
        'role' => GroupTeacherRole::Lead,
        'assigned_from' => '2026-02-01',
        'assigned_to' => null,
    ], $overrides);
}

beforeEach(function (): void {
    $this->action = app(AssignTeacherAction::class);
    $this->group = Group::factory()->active()->create();
});

it('assigns a teacher to an open group and publishes the event', function (): void {
    Event::fake([TeacherAssignedToGroup::class]);

    $data = assignmentData(['role' => GroupTeacherRole::Assistant]);

    $assignment = app(AssignTeacherAction::class)->execute($this->group, $data);

    expect($assignment->exists)->toBeTrue()
        ->and($assignment->role)->toBe(GroupTeacherRole::Assistant)
        ->and($assignment->isOpen())->toBeTrue();

    Event::assertDispatched(
        TeacherAssignedToGroup::class,
        fn (TeacherAssignedToGroup $event): bool => $event->groupId === (string) $this->group->getKey()
            && $event->role === GroupTeacherRole::Assistant->value
            && $event->courseId === null,
    );
});

it('rejects assigning the same teacher twice for the same course', function (): void {
    $data = assignmentData();

    $this->action->execute($this->group, $data);
    $this->action->execute($this->group, $data);
})->throws(BusinessRuleViolation::class);

it('allows two different teachers without a course', function (): void {
    $first = $this->action->execute($this->group, assignmentData());
    $second = $this->action->execute($this->group, assignmentData());

    expect(GroupTeacher::query()->forGroup((string) $this->group->getKey())->count())->toBe(2)
        ->and((string) $first->getKey())->not->toBe((string) $second->getKey());
});

it('rejects assignments on a completed group', function (): void {
    $completed = Group::factory()->completed()->create();

    $this->action->execute($completed, assignmentData());
})->throws(BusinessRuleViolation::class);

it('unassigns by closing the record instead of deleting it', function (): void {
    $assignment = $this->action->execute($this->group, assignmentData());

    $closed = app(UnassignTeacherAction::class)->execute($assignment);

    expect($closed->isOpen())->toBeFalse()
        ->and($closed->assigned_to)->not->toBeNull()
        ->and(GroupTeacher::query()->find($assignment->getKey()))->not->toBeNull();
});

it('rejects unassigning an already closed assignment', function (): void {
    $assignment = $this->action->execute($this->group, assignmentData());

    app(UnassignTeacherAction::class)->execute($assignment);
    app(UnassignTeacherAction::class)->execute($assignment->refresh());
})->throws(BusinessRuleViolation::class);

it('keeps planning groups mutable but completed ones frozen', function (): void {
    expect($this->group->status)->toBe(GroupStatus::Active)
        ->and(GroupStatus::Completed->acceptsMembers())->toBeFalse()
        ->and(GroupStatus::Planning->acceptsMembers())->toBeTrue();
});
