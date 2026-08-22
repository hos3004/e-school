<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Groups\Application\Actions\EnrollStudentAction;
use Modules\Groups\Application\Actions\WithdrawStudentAction;
use Modules\Groups\Database\Factories\GroupMembershipFactory;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Events\StudentEnrolledInGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = app(EnrollStudentAction::class);
});

it('enrolls a student into an active group and publishes the event', function (): void {
    Event::fake([StudentEnrolledInGroup::class]);

    $group = Group::factory()->active()->create(['capacity' => 10]);
    $studentId = GroupMembershipFactory::ensureStudentProfile();

    $membership = app(EnrollStudentAction::class)->execute($group, $studentId);

    expect($membership->exists)->toBeTrue()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->left_at)->toBeNull()
        ->and((string) $membership->group_id)->toBe((string) $group->getKey());

    Event::assertDispatched(
        StudentEnrolledInGroup::class,
        fn (StudentEnrolledInGroup $event): bool => $event->studentProfileId === $studentId
            && $event->groupId === (string) $group->getKey(),
    );
});

it('rejects enrolling into a planning group', function (): void {
    $group = Group::factory()->create();

    $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
})->throws(BusinessRuleViolation::class);

it('rejects enrolling into a completed group', function (): void {
    $group = Group::factory()->completed()->create();

    $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
})->throws(BusinessRuleViolation::class);

it('rejects enrolling beyond capacity', function (): void {
    $group = Group::factory()->active()->create(['capacity' => 2]);

    $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
    $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
    $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
})->throws(BusinessRuleViolation::class);

it('counts only active memberships against capacity', function (): void {
    $group = Group::factory()->active()->create(['capacity' => 1]);

    $first = $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());
    app(WithdrawStudentAction::class)->execute($first->refresh(), 'انتقال لأخرى');

    $second = $this->action->execute($group->refresh(), GroupMembershipFactory::ensureStudentProfile());

    expect($second->status)->toBe(MembershipStatus::Active);
});

it('rejects a duplicate active enrollment for the same student', function (): void {
    $group = Group::factory()->active()->create();
    $studentId = GroupMembershipFactory::ensureStudentProfile();

    $this->action->execute($group, $studentId);
    $this->action->execute($group, $studentId);
})->throws(BusinessRuleViolation::class);

it('allows re-enrollment after leaving', function (): void {
    $group = Group::factory()->active()->create();
    $studentId = GroupMembershipFactory::ensureStudentProfile();

    $first = $this->action->execute($group, $studentId);
    app(WithdrawStudentAction::class)->execute($first, 'ظرف عائلي');

    $second = $this->action->execute($group->refresh(), $studentId);

    expect($second->status)->toBe(MembershipStatus::Active)
        ->and(GroupMembership::query()->forStudent($studentId)->count())->toBe(2);
});

it('requires a reason to withdraw and transitions status via canTransitionTo', function (): void {
    $group = Group::factory()->active()->create();
    $membership = $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());

    app(WithdrawStudentAction::class)->execute($membership, '  ');
})->throws(BusinessRuleViolation::class);

it('withdraws with a reason without deleting the record', function (): void {
    $group = Group::factory()->active()->create();
    $membership = $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());

    $withdrawn = app(WithdrawStudentAction::class)->execute($membership->refresh(), 'انسحاب');

    expect($withdrawn->status)->toBe(MembershipStatus::Left)
        ->and($withdrawn->left_at)->not->toBeNull()
        ->and(GroupMembership::query()->find($membership->getKey()))->not->toBeNull();
});

it('rejects withdrawing an already left membership', function (): void {
    $group = Group::factory()->active()->create();
    $membership = $this->action->execute($group, GroupMembershipFactory::ensureStudentProfile());

    app(WithdrawStudentAction::class)->execute($membership, 'أول مرة');
    app(WithdrawStudentAction::class)->execute($membership->refresh(), 'ثانية');
})->throws(BusinessRuleViolation::class);
