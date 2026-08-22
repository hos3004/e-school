<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Events\StudentProfileUpdated;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function createStudent(): StudentProfile
{
    return StudentProfile::factory()->create([
        'organization_id' => Fixtures::organizationId(),
        'user_id' => Fixtures::userId(),
        'student_code' => 'STU-UP-'.str()->random(4),
    ]);
}

it('updates changed fields only and publishes the event with primitives', function (): void {
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
            && !isset($event->changes['student_code']),
    );
});

it('publishes nothing when nothing changed', function (): void {
    $student = createStudent();
    Event::fake([StudentProfileUpdated::class]);

    app(UpdateStudentProfileAction::class)->execute($student, [
        'city' => $student->city,
    ]);

    Event::assertNotDispatched(StudentProfileUpdated::class);
});

it('ignores non-editable fields such as student_code', function (): void {
    $student = createStudent();

    app(UpdateStudentProfileAction::class)->execute($student, [
        'student_code' => 'HACKED-1',
        'user_id' => (string) str()->ulid(),
    ]);

    expect($student->refresh()->student_code)->not->toBe('HACKED-1');
});

it('refuses to update an archived student', function (): void {
    $student = createStudent();
    app(ArchiveStudentAction::class)->execute($student, 'انتقال العائلة');

    app(UpdateStudentProfileAction::class)->execute($student, ['city' => 'Luxor']);
})->throws(BusinessRuleViolation::class);
