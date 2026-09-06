<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * بيانات مخالفة — مؤسسة جديدة لكل استدعاء إلا إذا مُرِّر enrollment_id
 * صريحًا لتجميع عدّاد نفس الطالب.
 *
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function violationData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => (string) str()->ulid(),
        'type' => ViolationType::UnexcusedAbsence->value,
        'occurred_at' => now()->toIso8601String(),
    ], $overrides);
}

it('records a countable violation and publishes ViolationRecorded', function (): void {
    config()->set('discipline.counter_window', 'rolling');
    Event::fake([ViolationRecorded::class, DisciplineActionApplied::class]);

    $violation = app(RecordViolationAction::class)->execute(violationData());

    expect($violation->exists)->toBeTrue()
        ->and($violation->is_countable)->toBeTrue()
        ->and($violation->window_key)->toBe('R'.config('discipline.counter_window_days'));

    Event::assertDispatched(
        ViolationRecorded::class,
        fn (ViolationRecorded $event): bool => $event->payload()['enrollment_id'] === (string) $violation->enrollment_id
            && $event->payload()['count_in_window'] === 1,
    );

    expect(DisciplineAction::query()->sole()->action)->toBe(DisciplineActionType::Notice);

    Event::assertDispatched(
        DisciplineActionApplied::class,
        fn (DisciplineActionApplied $event): bool => $event->thresholdReached === 1
            && $event->action === DisciplineActionType::Notice,
    );
});

it('marks non-countable types from config and never escalates them', function (): void {
    Event::fake();

    expect((bool) config('discipline.countable_events.excused_absence'))->toBeFalse();

    app(RecordViolationAction::class)->execute(violationData([
        'type' => ViolationType::ExcusedAbsence->value,
    ]));

    expect(ViolationEvent::query()->latest('id')->first()->is_countable)->toBeFalse()
        ->and(DisciplineAction::query()->count())->toBe(0);

    Event::assertDispatched(ViolationRecorded::class);
});

it('escalates to freeze at the configured third threshold exactly once', function (): void {
    config()->set('discipline.ladder', [
        ['threshold' => 3, 'action' => 'freeze_enrollment', 'automatic' => true],
    ]);

    Event::fake([DisciplineActionApplied::class]);

    $enrollmentId = (string) str()->ulid();
    $action = app(RecordViolationAction::class);

    $action->execute(violationData(['enrollment_id' => $enrollmentId, 'occurred_at' => now()->subDays(3)->toIso8601String()]));
    $action->execute(violationData(['enrollment_id' => $enrollmentId, 'occurred_at' => now()->subDays(2)->toIso8601String()]));

    expect(DisciplineAction::query()->count())->toBe(0);

    $action->execute(violationData(['enrollment_id' => $enrollmentId, 'occurred_at' => now()->subDay()->toIso8601String()]));
    $action->execute(violationData(['enrollment_id' => $enrollmentId, 'occurred_at' => now()->toIso8601String()]));

    expect(DisciplineAction::query()->count())->toBe(1);

    $applied = DisciplineAction::query()->sole();

    expect($applied->action)->toBe(DisciplineActionType::FreezeEnrollment)
        ->and((int) $applied->threshold_reached)->toBe(3)
        ->and((string) $applied->enrollment_id)->toBe($enrollmentId)
        ->and($applied->is_automatic)->toBeTrue();

    Event::assertDispatchedTimes(DisciplineActionApplied::class, 1);
});

it('counts only violations inside the configured rolling window', function (): void {
    config()->set([
        'discipline.counter_window' => 'rolling',
        'discipline.counter_window_days' => 30,
        'discipline.ladder' => [
            ['threshold' => 2, 'action' => 'warning'],
        ],
    ]);

    Event::fake([DisciplineActionApplied::class]);

    $enrollmentId = (string) str()->ulid();
    $action = app(RecordViolationAction::class);

    $action->execute(violationData([
        'enrollment_id' => $enrollmentId,
        'occurred_at' => now()->subDays(31)->toIso8601String(),
    ]));

    $current = $action->execute(violationData([
        'enrollment_id' => $enrollmentId,
        'occurred_at' => now()->toIso8601String(),
    ]));

    expect($action->countInWindow($current))->toBe(1)
        ->and(DisciplineAction::query()->count())->toBe(0);

    Event::assertNotDispatched(DisciplineActionApplied::class);
});

it('rejects a violation type that is unknown to the enum', function (): void {
    app(RecordViolationAction::class)->execute(violationData([
        'type' => 'mystery_type',
    ]));
})->throws(ValueError::class);

it('rejects a type missing from the discipline settings map', function (): void {
    config()->set([
        'discipline.countable_events' => ['unexcused_absence' => true],
        'discipline.ladder' => [['threshold' => 1, 'action' => 'notice']],
    ]);

    app(RecordViolationAction::class)->execute(violationData([
        'type' => ViolationType::TeacherAbsence->value,
    ]));
})->throws(BusinessRuleViolation::class);
