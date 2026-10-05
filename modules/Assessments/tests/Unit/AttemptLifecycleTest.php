<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Assessments\Application\Actions\GradeAttemptAction;
use Modules\Assessments\Application\Actions\StartAttemptAction;
use Modules\Assessments\Application\Actions\SubmitAttemptAction;
use Modules\Assessments\Domain\Events\AttemptGraded;
use Modules\Assessments\Domain\Events\AttemptStarted;
use Modules\Assessments\Domain\Events\AttemptSubmitted;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * اختبار ببنك أسئلة مكتمل — مجموع درجات الأسئلة يساوي الدرجة الكلية،
 * وهو الشرط الذي يفتح بدء المحاولة.
 *
 * @param array<string, mixed> $attributes
 * @return array{0: Assessment, 1: list<string>} [الاختبار, معرّفات الأسئلة]
 */
function assessmentWithQuestionBank(array $attributes = []): array
{
    $assessment = Assessment::factory()->create($attributes);
    $total = (int) $assessment->total_score;
    $half = intdiv($total, 2);

    $questionIds = [];
    foreach ([$half, $total - $half] as $index => $score) {
        $questionIds[] = (string) Question::factory()->create([
            'assessment_id' => $assessment->getKey(),
            'score' => $score,
            'sort_order' => $index + 1,
        ])->getKey();
    }

    return [$assessment->refresh(), $questionIds];
}

it('starts an attempt inside the availability window and publishes AttemptStarted', function (): void {
    Event::fake([AttemptStarted::class]);

    [$assessment] = assessmentWithQuestionBank([
        'max_attempts' => 2,
        'available_from' => CarbonImmutable::now('UTC')->subHour(),
        'available_to' => CarbonImmutable::now('UTC')->addDays(7),
    ]);
    $studentProfileId = Fixtures::studentProfileId();

    $attempt = app(StartAttemptAction::class)->execute($assessment, $studentProfileId);

    expect($attempt->attempt_number)->toBe(1)
        ->and($attempt->submitted_at)->toBeNull();

    Event::assertDispatched(AttemptStarted::class, fn (AttemptStarted $e): bool => $e->payload()['attempt_number'] === 1);
});

it('rejects starting an attempt outside the availability window', function (): void {
    [$assessment] = assessmentWithQuestionBank([
        'available_from' => CarbonImmutable::now('UTC')->addDay(),
        'available_to' => CarbonImmutable::now('UTC')->addWeek(),
    ]);

    app(StartAttemptAction::class)->execute(
        $assessment,
        Fixtures::studentProfileId(),
    );
})->throws(BusinessRuleViolation::class);

it('rejects attempts beyond the configured maximum', function (): void {
    [$assessment] = assessmentWithQuestionBank([
        'max_attempts' => 1,
        'available_from' => CarbonImmutable::now('UTC')->subHour(),
        'available_to' => CarbonImmutable::now('UTC')->addDays(7),
    ]);
    $action = app(StartAttemptAction::class);
    $studentProfileId = Fixtures::studentProfileId();

    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'student_profile_id' => $studentProfileId,
        'attempt_number' => 1,
    ]);

    $action->execute($assessment, $studentProfileId);
})->throws(BusinessRuleViolation::class);

it('submits answers once and locks them afterwards', function (): void {
    Event::fake([AttemptSubmitted::class]);

    [$assessment, $questionIds] = assessmentWithQuestionBank(['duration_minutes' => 60]);

    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'started_at' => CarbonImmutable::now('UTC')->subMinutes(10),
    ]);

    $answers = [
        $questionIds[0] => ['key' => 'a'],
        $questionIds[1] => ['key' => 'b'],
    ];

    $updated = app(SubmitAttemptAction::class)->execute($attempt, $answers);

    expect($updated->answers)->toBe($answers)
        ->and($updated->submitted_at)->not->toBeNull();

    expect(fn (): AssessmentAttempt => app(SubmitAttemptAction::class)->execute(
        $attempt->refresh(),
        $answers,
    ))->toThrow(BusinessRuleViolation::class);
});

it('rejects a submission whose answers do not cover every question', function (): void {
    [$assessment, $questionIds] = assessmentWithQuestionBank(['duration_minutes' => 60]);

    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'started_at' => CarbonImmutable::now('UTC')->subMinutes(10),
    ]);

    app(SubmitAttemptAction::class)->execute($attempt, [$questionIds[0] => ['key' => 'a']]);
})->throws(BusinessRuleViolation::class);

it('refuses to start an attempt while the question bank is incomplete', function (): void {
    $assessment = Assessment::factory()->availableNow()->create(['total_score' => 100]);

    app(StartAttemptAction::class)->execute($assessment, Fixtures::studentProfileId());
})->throws(BusinessRuleViolation::class);

it('rejects submissions after the duration plus grace window', function (): void {
    config()->set('assessments.submission.grace_minutes', 5);

    $assessment = Assessment::factory()->create(['duration_minutes' => 30]);

    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'started_at' => CarbonImmutable::now('UTC')->subMinutes(40),
    ]);

    app(SubmitAttemptAction::class)->execute($attempt, []);
})->throws(BusinessRuleViolation::class);

it('grades a submitted attempt and derives the pass result from the stored passing score', function (): void {
    Event::fake([AttemptGraded::class]);

    $assessment = Assessment::factory()->create(['total_score' => 100, 'passing_score' => 50]);
    $actorId = Fixtures::userId();

    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->id,
    ]);

    $graded = app(GradeAttemptAction::class)->execute(
        $attempt,
        70,
        actorId: $actorId,
        reason: 'تصحيح المحاولة وفق نموذج الإجابة',
    );

    expect($graded->score)->toBe(70)
        ->and($graded->passed)->toBeTrue()
        ->and($graded->graded_by)->toBe($actorId);

    Event::assertDispatched(AttemptGraded::class, fn (AttemptGraded $e): bool => $e->payload()['passed'] === true);
});

it('marks the attempt as failed below the passing score', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 100, 'passing_score' => 80]);

    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->id,
    ]);

    $graded = app(GradeAttemptAction::class)->execute(
        $attempt,
        55,
        actorId: Fixtures::userId(),
        reason: 'تصحيح المحاولة وفق نموذج الإجابة',
    );

    expect($graded->passed)->toBeFalse();
});

it('refuses to grade before submission', function (): void {
    $attempt = AssessmentAttempt::factory()->create();

    app(GradeAttemptAction::class)->execute(
        $attempt,
        90,
        actorId: Fixtures::userId(),
        reason: 'محاولة تصحيح قبل التسليم',
    );
})->throws(BusinessRuleViolation::class);

it('locks the grade after the first grading', function (): void {
    $attempt = AssessmentAttempt::factory()->graded(70, true)->create();

    app(GradeAttemptAction::class)->execute(
        $attempt->refresh(),
        90,
        actorId: Fixtures::userId(),
        reason: 'محاولة إعادة تصحيح بعد الاعتماد',
    );
})->throws(BusinessRuleViolation::class);

it('rejects scores outside the assessment total range', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 50]);

    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->id,
    ]);

    app(GradeAttemptAction::class)->execute(
        $attempt,
        51,
        actorId: Fixtures::userId(),
        reason: 'درجة أعلى من الدرجة الكلية',
    );
})->throws(BusinessRuleViolation::class);
