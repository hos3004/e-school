<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Application\Actions\WaiveViolationAction;
use Modules\Discipline\Domain\Events\ViolationWaived;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

it('waives a violation with a documented reason and publishes ViolationWaived', function (): void {
    Event::fake([ViolationWaived::class]);

    $violation = app(RecordViolationAction::class)->execute([
        'organization_id' => disciplineOrg(),
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => (string) str()->ulid(),
        'type' => 'unexcused_absence',
    ]);

    $waived = app(WaiveViolationAction::class)->execute($violation, [
        'reason' => 'عذر طبي موثّق من المستشفى',
    ]);

    expect($waived->refresh()->isWaived())->toBeTrue()
        ->and($waived->waiver_reason)->toBe('عذر طبي موثّق من المستشفى')
        ->and($waived->waived_at)->not->toBeNull();

    Event::assertDispatched(
        ViolationWaived::class,
        fn (ViolationWaived $event): bool => $event->payload()['count_in_window_after_waiver'] === 0,
    );
});

it('refuses to waive the same violation twice', function (): void {
    $violation = app(RecordViolationAction::class)->execute([
        'organization_id' => disciplineOrg(),
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => (string) str()->ulid(),
        'type' => 'unexcused_absence',
    ]);

    app(WaiveViolationAction::class)->execute($violation, ['reason' => 'عذر مقبول']);
    app(WaiveViolationAction::class)->execute($violation, ['reason' => 'عذر آخر']);
})->throws(BusinessRuleViolation::class);

it('keeps the violation record in place after waiver — no deletion path', function (): void {
    $violation = app(RecordViolationAction::class)->execute([
        'organization_id' => disciplineOrg(),
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => (string) str()->ulid(),
        'type' => 'unexcused_absence',
    ]);

    app(WaiveViolationAction::class)->execute($violation, ['reason' => 'خطأ في الرصد']);

    expect(ViolationEvent::query()->whereKey($violation->getKey())->exists())->toBeTrue();
});
