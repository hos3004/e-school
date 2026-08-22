<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Domain\Events\CourseCreated;
use Modules\Academics\Domain\Models\Level;

uses(RefreshDatabase::class);

function courseData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => (string) str()->ulid(),
        'level_id' => Level::factory()->create()->getKey(),
        'code' => 'CRS-TEST',
        'name' => ['ar' => 'كورس تجريبي', 'en' => 'Demo Course'],
        'total_sessions' => 12,
        'is_active' => true,
    ], $overrides);
}

it('creates a course and publishes CourseCreated', function () {
    Event::fake([CourseCreated::class]);

    $course = app(CreateCourseAction::class)->execute(courseData());

    expect($course->exists)->toBeTrue()
        ->and($course->total_sessions)->toBe(12);

    Event::assertDispatched(
        CourseCreated::class,
        fn (CourseCreated $event): bool => $event->courseId === (string) $course->getKey()
            && $event->payload()['total_sessions'] === 12
    );
});

it('rejects a duplicate course code even across archived courses', function () {
    $action = app(CreateCourseAction::class);
    $data = courseData();

    $first = $action->execute($data);
    $first->delete();

    $action->execute(courseData(['organization_id' => (string) str()->ulid()]));
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects zero or negative total sessions', function () {
    app(CreateCourseAction::class)->execute(courseData(['total_sessions' => 0]));
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects a course for a missing level', function () {
    app(CreateCourseAction::class)->execute(courseData([
        'level_id' => (string) str()->ulid(),
    ]));
})->throws(Shared\Support\BusinessRuleViolation::class);
