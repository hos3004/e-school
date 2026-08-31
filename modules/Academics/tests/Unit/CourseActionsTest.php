<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\ArchiveCourseAction;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Domain\Events\CourseArchived;
use Modules\Academics\Domain\Events\CourseUpdated;
use Modules\Academics\Domain\Models\Course;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

it('updates course fields and publishes CourseUpdated', function (): void {
    Event::fake([CourseUpdated::class]);

    $course = Course::factory()->create(['total_sessions' => 10]);

    $updated = app(UpdateCourseAction::class)->execute($course, [
        'total_sessions' => 20,
    ]);

    expect($updated->total_sessions)->toBe(20);

    Event::assertDispatched(
        CourseUpdated::class,
        fn (CourseUpdated $event): bool => $event->courseId === (string) $course->getKey()
            && $event->changedFields === ['total_sessions'],
    );
});

it('rejects taking another course code', function (): void {
    Course::factory()->create(['code' => 'TAKEN']);

    $course = Course::factory()->create(['code' => 'MINE']);

    app(UpdateCourseAction::class)->execute($course, ['code' => 'TAKEN']);
})->throws(BusinessRuleViolation::class);

it('archives a course and publishes CourseArchived with the reason', function (): void {
    Event::fake([CourseArchived::class]);

    $course = Course::factory()->create();

    $archived = app(ArchiveCourseAction::class)->execute($course, 'توقف الطلب على الكورس');

    expect($archived->trashed())->toBeTrue();

    Event::assertDispatched(
        CourseArchived::class,
        fn (CourseArchived $event): bool => $event->courseId === (string) $course->getKey()
            && $event->reason === 'توقف الطلب على الكورس',
    );
});
