<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Discipline\Application\Actions\DecideReactivationAction;
use Modules\Discipline\Application\Actions\RequestReactivationAction;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Events\ReactivationRequested;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function reactivationData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => disciplineOrg(),
        'enrollment_id' => (string) str()->ulid(),
        'student_statement' => 'أتعهد بالحضور المنتظم والتزام كامل بمواعيد الحصص القادمة.',
    ], $overrides);
}

it('submits a pending request as attempt one and publishes ReactivationRequested', function (): void {
    Event::fake([ReactivationRequested::class]);

    $request = app(RequestReactivationAction::class)->execute(reactivationData());

    expect($request->status)->toBe(ReactivationStatus::Pending)
        ->and($request->attempt_number)->toBe(1)
        ->and((string) $request->requested_by)->toBe((string) $this->user->getKey());

    Event::assertDispatched(
        ReactivationRequested::class,
        fn (ReactivationRequested $event): bool => $event->payload()['attempt_number'] === 1,
    );
});

it('refuses a second request while one is still open', function (): void {
    $action = app(RequestReactivationAction::class);

    $action->execute(reactivationData());
    $action->execute(reactivationData());
})->throws(BusinessRuleViolation::class);

it('counts closed attempts against the configured maximum', function (): void {
    config()->set('discipline.reactivation.max_attempts', 1);

    $enrollmentId = (string) str()->ulid();
    $action = app(RequestReactivationAction::class);

    $first = $action->execute(reactivationData(['enrollment_id' => $enrollmentId]));

    // حسم أول طلب بالرفض — محاولتا التقديم اللاحقتان مرفوضتان لأن الحد الأقصى 1.
    app(DecideReactivationAction::class)->execute($first, [
        'decision' => ReactivationStatus::Rejected,
        'decision_note' => 'بيان غير كافٍ',
    ]);

    expect($first->refresh()->status)->toBe(ReactivationStatus::Rejected)
        ->and($first->reviewed_at)->not->toBeNull();

    $action->execute(reactivationData(['enrollment_id' => $enrollmentId]));
})->throws(BusinessRuleViolation::class);

it('enforces the configured cooldown between attempts', function (): void {
    config()->set([
        'discipline.reactivation.max_attempts' => 3,
        'discipline.reactivation.cooldown_days_between_attempts' => 7,
    ]);

    $enrollmentId = (string) str()->ulid();
    $action = app(RequestReactivationAction::class);

    $first = $action->execute(reactivationData(['enrollment_id' => $enrollmentId]));

    app(DecideReactivationAction::class)->execute($first, [
        'decision' => ReactivationStatus::Rejected,
        'decision_note' => 'رفض أول',
    ]);

    // تقديم فوري بعد القرار — داخل فترة التهدئة.
    $action->execute(reactivationData(['enrollment_id' => $enrollmentId]));
})->throws(BusinessRuleViolation::class);
