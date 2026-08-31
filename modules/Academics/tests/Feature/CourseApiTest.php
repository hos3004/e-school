<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Events\CourseArchived;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('course.manage', fn ($user) => true);
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function coursePayload(array $overrides = []): array
{
    return array_merge([
        'level_id' => Level::factory()->create()->getKey(),
        'code' => 'CRS-'.strtoupper(str()->random(5)),
        'name' => ['ar' => 'كورس جديد', 'en' => 'New Course'],
        'total_sessions' => 10,
        'session_mode' => 'both',
        'reason' => 'إنشاء كورس للاختبار',
    ], $overrides);
}

it('creates a course through the API and returns 201', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/academics/courses', coursePayload());

    $response->assertCreated()
        ->assertJsonPath('data.total_sessions', 10);

    expect(Course::query()->whereKey($response->json('data.id'))->exists())->toBeTrue();
});

it('rejects duplicate course codes with a validation error', function (): void {
    $user = User::factory()->create();
    Course::factory()->create(['code' => 'DUP-CRS']);

    $this->actingAs($user)
        ->postJson('/api/academics/courses', coursePayload(['code' => 'DUP-CRS']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('updates a course through the API', function (): void {
    $user = User::factory()->create();
    $course = Course::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->putJson("/api/academics/courses/{$course->getKey()}", [
            'total_sessions' => 18,
            'completion_rules' => ['min_attendance_percent' => 75],
            'reason' => 'تحديث قواعد الإكمال',
        ])
        ->assertOk()
        ->assertJsonPath('data.total_sessions', 18);

    expect($course->fresh()->completion_rules)->toBe(['min_attendance_percent' => 75]);
});

it('archives a course and publishes the event with the reason', function (): void {
    Event::fake([CourseArchived::class]);

    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/academics/courses/{$course->getKey()}", [
            'reason' => 'دمج الكورس مع كورس آخر',
        ])
        ->assertOk();

    expect($course->fresh()->trashed())->toBeTrue();

    Event::assertDispatched(
        CourseArchived::class,
        fn (CourseArchived $event): bool => $event->reason === 'دمج الكورس مع كورس آخر',
    );
});

it('rejects archiving a course without a reason', function (): void {
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/academics/courses/{$course->getKey()}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});
