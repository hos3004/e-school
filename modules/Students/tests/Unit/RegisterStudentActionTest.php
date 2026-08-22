<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Events\StudentRegistered;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

function studentData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => (string) str()->ulid(),
        'user_id' => (string) str()->ulid(),
        'student_code' => 'STU-0001',
        'date_of_birth' => '2010-05-14',
        'gender' => StudentGender::Male->value,
        'nationality' => 'EG',
        'country' => 'EG',
        'city' => 'Cairo',
        'preferred_language' => 'ar',
        'joined_at' => '2026-01-10',
    ], $overrides);
}

it('registers a student and publishes StudentRegistered', function () {
    Event::fake([StudentRegistered::class]);

    $student = app(RegisterStudentAction::class)->execute(studentData());

    expect($student->exists)->toBeTrue()
        ->and($student->student_code)->toBe('STU-0001')
        ->and($student->gender)->toBeInstanceOf(StudentGender::class);

    Event::assertDispatched(
        StudentRegistered::class,
        fn (StudentRegistered $event): bool => $event->studentId === (string) $student->getKey()
            && $event->userId === (string) $student->user_id
            && $event->payload()['student_code'] === 'STU-0001'
    );
});

it('rejects registering the same user twice', function () {
    $data = studentData();

    app(RegisterStudentAction::class)->execute($data);

    app(RegisterStudentAction::class)->execute(studentData([
        'student_code' => 'STU-0002',
    ]));
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects a duplicate student code even across archived profiles', function () {
    $first = app(RegisterStudentAction::class)->execute(studentData());
    $first->delete();

    app(RegisterStudentAction::class)->execute(studentData([
        'user_id' => (string) str()->ulid(),
    ]));
})->throws(Shared\Support\BusinessRuleViolation::class);
