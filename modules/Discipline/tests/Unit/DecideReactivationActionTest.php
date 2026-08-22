<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Models\User;
use Modules\Discipline\Application\Actions\DecideReactivationAction;
use Modules\Discipline\Database\Factories\ReactivationRequestFactory;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Events\ReactivationReviewed;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reviewer = User::factory()->create();
    $this->actingAs($this->reviewer);
});

it('approves a pending request when an assessment attempt is linked', function () {
    config()->set('discipline.reactivation.requires_assessment', true);
    Event::fake([ReactivationReviewed::class]);

    $request = ReactivationRequestFactory::new()->create();

    $decided = app(DecideReactivationAction::class)->execute($request, [
        'decision' => ReactivationStatus::Approved,
        'decision_note' => 'اجتاز اختبار الجدية بنجاح',
        'assessment_attempt_id' => (string) str()->ulid(),
    ]);

    expect($decided->status)->toBe(ReactivationStatus::Approved)
        ->and((string) $decided->reviewer_id)->toBe((string) $this->reviewer->getKey())
        ->and($decided->reviewed_at)->not->toBeNull();

    Event::assertDispatched(
        ReactivationReviewed::class,
        fn (ReactivationReviewed $event): bool => $event->payload()['decision'] === 'approved'
    );
});

it('refuses approval without an assessment result when the setting requires it', function () {
    config()->set('discipline.reactivation.requires_assessment', true);

    $request = ReactivationRequestFactory::new()->create();

    app(DecideReactivationAction::class)->execute($request, [
        'decision' => ReactivationStatus::Approved,
        'decision_note' => 'قبول بلا اختبار',
    ]);
})->throws(Shared\Support\BusinessRuleViolation::class);

it('allows rejection without any assessment', function () {
    config()->set('discipline.reactivation.requires_assessment', true);

    $request = ReactivationRequestFactory::new()->create();

    $decided = app(DecideReactivationAction::class)->execute($request, [
        'decision' => ReactivationStatus::Rejected,
        'decision_note' => 'البيان غير كافٍ',
    ]);

    expect($decided->status)->toBe(ReactivationStatus::Rejected)
        ->and($decided->assessment_attempt_id)->toBeNull();
});

it('refuses to decide an already closed request — state machine guard', function () {
    $reviewerId = (string) $this->reviewer->getKey();

    $request = ReactivationRequestFactory::new()->rejected($reviewerId)->create();

    expect($request->status->canTransitionTo(ReactivationStatus::Approved))->toBeFalse();

    app(DecideReactivationAction::class)->execute($request, [
        'decision' => ReactivationStatus::Approved,
        'decision_note' => 'محاولة حسم طلب مغلق',
        'assessment_attempt_id' => (string) str()->ulid(),
    ]);
})->throws(Shared\Support\BusinessRuleViolation::class);

it('accepts only approve or reject as decisions', function () {
    $request = ReactivationRequestFactory::new()->create();

    app(DecideReactivationAction::class)->execute($request, [
        'decision' => ReactivationStatus::UnderReview,
        'decision_note' => 'تحويل للاختبار',
    ]);
})->throws(Shared\Support\BusinessRuleViolation::class);
