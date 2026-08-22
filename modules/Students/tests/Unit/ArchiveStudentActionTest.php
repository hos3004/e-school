<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Domain\Events\StudentArchived;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

function createArchivableStudent(): StudentProfile
{
    return app(RegisterStudentAction::class)->execute([
        'organization_id' => (string) str()->ulid(),
        'user_id' => (string) str()->ulid(),
        'student_code' => 'STU-AR-'.str()->random(4),
    ]);
}

it('soft-deletes the profile and publishes the event with the reason', function () {
    $student = createArchivableStudent();
    Event::fake([StudentArchived::class]);

    app(ArchiveStudentAction::class)->execute($student, 'انتقال العائلة إلى مدينة أخرى');

    expect($student->fresh())->not->toBeNull()
        ->and($student->refresh()->trashed())->toBeTrue();

    Event::assertDispatched(
        StudentArchived::class,
        fn (StudentArchived $event): bool => $event->studentId === (string) $student->getKey()
            && $event->reason === 'انتقال العائلة إلى مدينة أخرى'
    );
});

it('requires a non-empty reason', function () {
    $student = createArchivableStudent();

    app(ArchiveStudentAction::class)->execute($student, '   ');
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects archiving an already archived student', function () {
    $student = createArchivableStudent();
    app(ArchiveStudentAction::class)->execute($student, 'سبب أول');

    app(ArchiveStudentAction::class)->execute($student, 'سبب ثانٍ');
})->throws(Shared\Support\BusinessRuleViolation::class);
