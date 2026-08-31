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
        'course_id' => Fixtures::courseId(),
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

/**
 * معلم معتمد على كورس بعينه — المسار الحقيقي لإنشاء اختبار بلا صلاحية إدارية شاملة.
 *
 * @return array{0: string, 1: string} [actorId, courseId]
 */
function qualifiedAssessmentAuthor(): array
{
    $courseId = Fixtures::courseId();
    $actorId = Fixtures::userId();
    Fixtures::qualifyTeacher(Fixtures::staffProfileForUser($actorId), $courseId);

    return [$actorId, $courseId];
}

/**
 * سؤال اختيار متعدد صالح — خياران فريدان وإجابة صحيحة من بينهما.
 *
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function mcqQuestionData(array $overrides = []): array
{
    return array_merge([
        'type' => QuestionType::Mcq,
        'body' => ['ar' => 'سؤال', 'en' => 'Question'],
        'score' => 5,
        'options' => [
            ['key' => 'a', 'text' => ['ar' => 'الخيار الأول', 'en' => 'First option']],
            ['key' => 'b', 'text' => ['ar' => 'الخيار الثاني', 'en' => 'Second option']],
        ],
        'correct_answer' => ['key' => 'a'],
    ], $overrides);
}

it('creates an assessment for a course the author is qualified to teach', function (): void {
    Event::fake([AssessmentCreated::class]);
    [$actorId, $courseId] = qualifiedAssessmentAuthor();

    $assessment = app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['course_id' => $courseId]),
        actorId: $actorId,
        reason: 'إنشاء اختبار الوحدة الأولى وفق خطة الكورس',
    );

    expect($assessment->exists)->toBeTrue()
        ->and($assessment->type)->toBe(AssessmentType::Quiz)
        ->and($assessment->course_id)->toBe($courseId);

    Event::assertDispatched(AssessmentCreated::class, fn (AssessmentCreated $e): bool => $e->name() === 'assessments.assessment_created'
        && $e->payload()['total_score'] === 100);
});

it('refuses an author who is not qualified for the course', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(),
        actorId: Fixtures::userId(),
        reason: 'محاولة إنشاء اختبار على كورس غير معتمد للمعلم',
    );
})->throws(BusinessRuleViolation::class);

it('requires a course for quizzes and exams', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['course_id' => null]),
        actorId: Fixtures::userId(),
        reason: 'اختبار بلا كورس',
        canManageAll: true,
    );
})->throws(BusinessRuleViolation::class);

it('rejects passing score above total score', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['passing_score' => 150]),
        actorId: Fixtures::userId(),
        reason: 'درجة نجاح أعلى من الدرجة الكلية',
        canManageAll: true,
    );
})->throws(BusinessRuleViolation::class);

it('rejects an inverted availability window', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload([
            'available_from' => CarbonImmutable::now('UTC')->addWeek(),
            'available_to' => CarbonImmutable::now('UTC')->addDay(),
        ]),
        actorId: Fixtures::userId(),
        reason: 'نافذة توفر مقلوبة',
        canManageAll: true,
    );
})->throws(BusinessRuleViolation::class);

it('rejects max attempts below one', function (): void {
    app(CreateAssessmentAction::class)->execute(
        assessmentPayload(['max_attempts' => 0]),
        actorId: Fixtures::userId(),
        reason: 'عدد محاولات غير صالح',
        canManageAll: true,
    );
})->throws(BusinessRuleViolation::class);

it('refuses to archive an assessment that already has attempts', function (): void {
    $assessment = Assessment::factory()->create();
    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
    ]);

    Event::fake([AssessmentArchived::class]);

    try {
        app(ArchiveAssessmentAction::class)->execute(
            $assessment,
            actorId: Fixtures::userId(),
            reason: 'محاولة أرشفة اختبار له محاولات',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation) {
        Event::assertNotDispatched(AssessmentArchived::class);
        expect($assessment->fresh())->not->toBeNull();
    }
});

it('archives an empty assessment and soft-deletes it', function (): void {
    Event::fake([AssessmentArchived::class]);

    $assessment = Assessment::factory()->create();

    app(ArchiveAssessmentAction::class)->execute(
        $assessment,
        actorId: Fixtures::userId(),
        reason: 'أرشفة اختبار لم يُستخدم',
    );

    expect($assessment->fresh()->trashed())->toBeTrue();
    Event::assertDispatched(AssessmentArchived::class);
});

it('adds a question with an auto-computed sort order', function (): void {
    Event::fake([QuestionAdded::class]);

    $assessment = Assessment::factory()->create(['total_score' => 100]);

    $question = app(AddQuestionAction::class)->execute(
        $assessment,
        mcqQuestionData(['body' => ['ar' => 'سؤال أول', 'en' => 'First question'], 'score' => 10]),
        actorId: Fixtures::userId(),
        reason: 'إضافة السؤال الأول لبنك الاختبار',
    );

    expect($question->sort_order)->toBe(1);
    Event::assertDispatched(QuestionAdded::class);
});

it('rejects an mcq question without two unique options and a matching answer', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 100]);

    app(AddQuestionAction::class)->execute(
        $assessment,
        mcqQuestionData(['correct_answer' => ['key' => 'z']]),
        actorId: Fixtures::userId(),
        reason: 'إجابة صحيحة خارج الخيارات',
    );
})->throws(BusinessRuleViolation::class);

it('rejects question scores exceeding the assessment total', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 20]);
    $action = app(AddQuestionAction::class);
    $actorId = Fixtures::userId();

    $action->execute(
        $assessment,
        mcqQuestionData(['score' => 15]),
        actorId: $actorId,
        reason: 'سؤال أول',
    );

    $action->execute(
        $assessment,
        mcqQuestionData(['body' => ['ar' => 'سؤال آخر'], 'score' => 15]),
        actorId: $actorId,
        reason: 'سؤال ثانٍ يتجاوز الدرجة الكلية',
    );
})->throws(BusinessRuleViolation::class);

it('rejects a duplicate sort order within the same assessment', function (): void {
    $assessment = Assessment::factory()->create();
    $action = app(AddQuestionAction::class);
    $actorId = Fixtures::userId();
    $data = mcqQuestionData(['sort_order' => 3]);

    $action->execute($assessment, $data, actorId: $actorId, reason: 'سؤال بترتيب محدد');

    $action->execute(
        $assessment,
        [...$data, 'body' => ['ar' => 'سؤال مختلف']],
        actorId: $actorId,
        reason: 'سؤال بنفس الترتيب',
    );
})->throws(BusinessRuleViolation::class);
