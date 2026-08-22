<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Assessments\Application\Actions\AddQuestionAction;
use Modules\Assessments\Application\Actions\ArchiveAssessmentAction;
use Modules\Assessments\Application\Actions\CreateAssessmentAction;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Domain\Events\AssessmentArchived;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Events\QuestionAdded;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function assessmentPayload(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'type' => AssessmentType::Quiz,
        'title' => ['ar' => 'اختبار الوحدة الأولى', 'en' => 'Unit one quiz'],
        'instructions' => ['ar' => 'أجب عن كل الأسئلة.'],
        'total_score' => 100,
        'passing_score' => 50,
        'duration_minutes' => 45,
        'max_attempts' => 2,
        'available_from' => CarbonImmutable::now('UTC')->subDay(),
        'available_to' => CarbonImmutable::now('UTC')->addWeek(),
    ], $overrides);
}

it('creates an assessment and publishes AssessmentCreated after success', function (): void {
    Event::fake([AssessmentCreated::class]);

    $assessment = app(CreateAssessmentAction::class)->execute(assessmentPayload(), actorId: Fixtures::userId());

    expect($assessment->exists)->toBeTrue()
        ->and($assessment->type)->toBe(AssessmentType::Quiz);

    Event::assertDispatched(AssessmentCreated::class, fn (AssessmentCreated $e): bool => $e->name() === 'assessments.assessment_created'
        && $e->payload()['total_score'] === 100);
});

it('rejects passing score above total score', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['passing_score' => 150]),
    );
})->throws(BusinessRuleViolation::class);

it('rejects an inverted availability window', function (): void {
    app(CreateAssessmentAction::class)->execute(assessmentPayload([
        'available_from' => CarbonImmutable::now('UTC')->addWeek(),
        'available_to' => CarbonImmutable::now('UTC')->addDay(),
    ]));
})->throws(BusinessRuleViolation::class);

it('rejects max attempts below one', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['max_attempts' => 0]),
    );
})->throws(BusinessRuleViolation::class);

it('refuses to archive an assessment that already has attempts', function (): void {
    $assessment = Assessment::factory()->create();
    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
    ]);

    Event::fake([AssessmentArchived::class]);

    try {
        app(ArchiveAssessmentAction::class)->execute($assessment);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation) {
        Event::assertNotDispatched(AssessmentArchived::class);
        expect($assessment->fresh())->not->toBeNull();
    }
});

it('archives an empty assessment and soft-deletes it', function (): void {
    Event::fake([AssessmentArchived::class]);

    $assessment = Assessment::factory()->create();

    app(ArchiveAssessmentAction::class)->execute($assessment);

    expect($assessment->fresh()->trashed())->toBeTrue();
    Event::assertDispatched(AssessmentArchived::class);
});

it('adds a question with an auto-computed sort order', function (): void {
    Event::fake([QuestionAdded::class]);

    $assessment = Assessment::factory()->create(['total_score' => 100]);
    $action = app(AddQuestionAction::class);

    $question = $action->execute($assessment, [
        'type' => QuestionType::Mcq,
        'body' => ['ar' => 'سؤال أول', 'en' => 'First question'],
        'score' => 10,
    ]);

    expect($question->sort_order)->toBe(1);
    Event::assertDispatched(QuestionAdded::class);
});

it('rejects question scores exceeding the assessment total', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 20]);
    $action = app(AddQuestionAction::class);

    $action->execute($assessment, [
        'type' => QuestionType::Mcq,
        'body' => ['ar' => 'سؤال'],
        'score' => 15,
    ]);

    $action->execute($assessment, [
        'type' => QuestionType::Mcq,
        'body' => ['ar' => 'سؤال آخر'],
        'score' => 15,
    ]);
})->throws(BusinessRuleViolation::class);

it('rejects a duplicate sort order within the same assessment', function (): void {
    $assessment = Assessment::factory()->create();
    $action = app(AddQuestionAction::class);
    $data = [
        'type' => QuestionType::Mcq,
        'body' => ['ar' => 'سؤال'],
        'score' => 5,
        'sort_order' => 3,
    ];

    $action->execute($assessment, $data);

    $action->execute($assessment, [...$data, 'body' => ['ar' => 'سؤال مختلف']]);
})->throws(BusinessRuleViolation::class);
