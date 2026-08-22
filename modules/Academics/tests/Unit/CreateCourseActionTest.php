<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Domain\Events\CourseCreated;
use Modules\Academics\Domain\Models\Level;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function courseData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'level_id' => Level::factory()->create()->getKey(),
        'code' => 'CRS-TEST',
        'name' => ['ar' => 'كورس تجريبي', 'en' => 'Demo Course'],
        'total_sessions' => 12,
        'is_active' => true,
    ], $overrides);
}

it('creates a course and publishes CourseCreated', function (): void {
    Event::fake([CourseCreated::class]);

    $course = app(CreateCourseAction::class)->execute(courseData());

    expect($course->exists)->toBeTrue()
        ->and($course->total_sessions)->toBe(12);

    Event::assertDispatched(
        CourseCreated::class,
        fn (CourseCreated $event): bool => $event->courseId === (string) $course->getKey()
            && $event->payload()['total_sessions'] === 12,
    );
});

it('rejects a duplicate course code even across archived courses', function (): void {
    $action = app(CreateCourseAction::class);
    $data = courseData();

    $first = $action->execute($data);
    $first->delete();

    $action->execute(courseData());
})->throws(BusinessRuleViolation::class);

it('rejects zero or negative total sessions', function (): void {
    app(CreateCourseAction::class)->execute(courseData(['total_sessions' => 0]));
})->throws(BusinessRuleViolation::class);

it('rejects a course for a missing level', function (): void {
    app(CreateCourseAction::class)->execute(courseData([
        'level_id' => (string) str()->ulid(),
    ]));
})->throws(BusinessRuleViolation::class);
