<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Events\StudentProfileUpdated;

uses(RefreshDatabase::class);

function createStudent(): Modules\Students\Domain\Models\StudentProfile
{
    return app(RegisterStudentAction::class)->execute([
        'organization_id' => (string) str()->ulid(),
        'user_id' => (string) str()->ulid(),
        'student_code' => 'STU-UP-'.str()->random(4),
    ]);
}

it('updates changed fields only and publishes the event with primitives', function () {
    $student = createStudent();
    Event::fake([StudentProfileUpdated::class]);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => 'Alexandria',
        'notes' => 'متفوق في الرياضيات',
    ]);

    expect($student->refresh()->city)->toBe('Alexandria');

    Event::assertDispatched(
        StudentProfileUpdated::class,
        fn (StudentProfileUpdated $event): bool => count($event->changes) === 2
            && isset($event->changes['city'], $event->changes['notes'])
            && ! isset($event->changes['student_code'])
    );
});

it('publishes nothing when nothing changed', function () {
    $student = createStudent();
    Event::fake([StudentProfileUpdated::class]);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => (string) $student->city,
    ]);

    Event::assertNotDispatched(StudentProfileUpdated::class);
});

it('ignores non-editable fields such as student_code', function () {
    $student = createStudent();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'student_code' => 'HACKED-1',
        'user_id' => (string) str()->ulid(),
    ]);

    expect($student->refresh()->student_code)->not->toBe('HACKED-1');
});

it('refuses to update an archived student', function () {
    $student = createStudent();
    app(ArchiveStudentAction::class)->execute($student, 'انتقال العائلة');

    app(UpdateStudentProfileAction::class)->execute($student, ['city' => 'Luxor']);
})->throws(Shared\Support\BusinessRuleViolation::class);
