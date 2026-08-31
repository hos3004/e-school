<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // القدرات الحقيقية التي تفحصها السياسات والطلبات — لا أسماء أدوار.
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('assessment.take', fn ($user): bool => true);
    Gate::define('grade.view', fn ($user): bool => true);
    Gate::define('program.manage', fn ($user): bool => true);
    // صلاحية إدارية شاملة: تعفي المنشئ من شرط الاعتماد على الكورس.
    Gate::define('settings.manage', fn ($user): bool => true);
});

function assessmentApiUser(): User
{
    return User::factory()->inOrganization(Fixtures::organizationId())->create();
}

function assessmentApiForeignOrganizationId(): string
{
    $organizationId = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $organizationId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Another organization'], JSON_UNESCAPED_UNICODE),
        'slug' => 'assessment-foreign-'.strtolower(substr($organizationId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $organizationId;
}

function assessmentJsonPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'quiz',
        'course_id' => Fixtures::courseId(),
        'title' => ['ar' => 'اختبار قصير', 'en' => 'Quick quiz'],
        'total_score' => 100,
        'passing_score' => 50,
        'max_attempts' => 2,
        'duration_minutes' => 30,
        'available_from' => now()->subHour()->toIso8601String(),
        'available_to' => now()->addWeek()->toIso8601String(),
        'reason' => 'إنشاء اختبار قصير ضمن خطة الكورس',
    ], $overrides);
}

it('creates an assessment via the API and publishes the domain event', function (): void {
    Event::fake([AssessmentCreated::class]);

    $response = $this->actingAs(assessmentApiUser())
        ->postJson('/api/assessments', assessmentJsonPayload());

    $response->assertCreated()
        ->assertJsonPath('data.type.value', 'quiz')
        ->assertJsonPath('data.total_score', 100);

    expect(Assessment::query()->whereKey($response->json('data.id'))->exists())->toBeTrue();

    Event::assertDispatched(AssessmentCreated::class);
});

it('validates the payload and returns translated validation errors', function (): void {
    $this->actingAs(assessmentApiUser())
        ->postJson('/api/assessments', assessmentJsonPayload([
            'passing_score' => 500,
            'available_from' => now()->addWeek()->toIso8601String(),
            'available_to' => now()->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['available_to']);
});

it('lists assessments scoped to the caller organization', function (): void {
    Assessment::factory()->count(2)->create();

    $this->actingAs(assessmentApiUser())
        ->getJson('/api/assessments')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('updates an assessment through the API', function (): void {
    // Quiz/exam assessments require a real course; the old fixture created an
    // impossible quiz with course_id=null and correctly tripped the domain guard.
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create();

    $this->actingAs(assessmentApiUser())
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 70,
            'reason' => 'رفع درجة النجاح بقرار اللجنة الأكاديمية',
        ])
        ->assertOk()
        ->assertJsonPath('data.passing_score', 70);
});

it('rejects an invalid assessment update without weakening domain validation', function (): void {
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create([
        'total_score' => 100,
    ]);

    $this->actingAs(assessmentApiUser())
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 101,
            'reason' => 'محاولة ضبط درجة نجاح أعلى من الدرجة الكلية',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.passing_score_above_total');
});

it('locks assessment settings after the first attempt', function (): void {
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create();
    AssessmentAttempt::factory()->create(['assessment_id' => $assessment->getKey()]);

    $this->actingAs(assessmentApiUser())
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 70,
            'reason' => 'محاولة تعديل إعداد مقفل بعد بدء المحاولات',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.settings_locked_after_attempts');
});

it('forbids assessment updates without the manage permission', function (): void {
    Gate::define('assessment.manage', fn ($user): bool => false);
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create();

    $this->actingAs(assessmentApiUser())
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 70,
            'reason' => 'طلب غير مصرح به',
        ])
        ->assertForbidden();
});

it('forbids assessment updates from another organization', function (): void {
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create();
    $outsider = User::factory()
        ->inOrganization(assessmentApiForeignOrganizationId())
        ->create();

    $this->actingAs($outsider)
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 70,
            'reason' => 'طلب من مؤسسة أخرى',
        ])
        ->assertForbidden();
});

it('requires authentication to update an assessment', function (): void {
    $assessment = Assessment::factory()->forCourse(Fixtures::courseId())->create();

    $this->patchJson("/api/assessments/{$assessment->getKey()}", [
        'passing_score' => 70,
        'reason' => 'طلب ضيف',
    ])->assertUnauthorized();
});

it('archives an assessment through the API', function (): void {
    $assessment = Assessment::factory()->create();

    $this->actingAs(assessmentApiUser())
        ->deleteJson("/api/assessments/{$assessment->getKey()}", [
            'reason' => 'أرشفة اختبار لم يُستخدم في أي محاولة',
        ])
        ->assertNoContent();

    expect($assessment->fresh()->trashed())->toBeTrue();
});
