<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // القدرات الحقيقية التي تفحصها السياسات والطلبات — لا أسماء أدوار.
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('assessment.take', fn ($user): bool => true);
    Gate::define('grade.view', fn ($user): bool => true);
    // صلاحية إدارية شاملة: تعفي المنشئ من شرط الاعتماد على الكورس.
    Gate::define('settings.manage', fn ($user): bool => true);
});

function assessmentApiUser(): User
{
    return User::factory()->inOrganization(Fixtures::organizationId())->create();
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
    $assessment = Assessment::factory()->create();

    $this->actingAs(assessmentApiUser())
        ->patchJson("/api/assessments/{$assessment->getKey()}", [
            'passing_score' => 70,
            'reason' => 'رفع درجة النجاح بقرار اللجنة الأكاديمية',
        ])
        ->assertOk()
        ->assertJsonPath('data.passing_score', 70);
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
