<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RestoreStudentAction;
use Modules\Students\Domain\Events\StudentRestored;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

it('restores an archived student and publishes the event', function (): void {
    $student = StudentProfile::factory()->create([
        'organization_id' => Fixtures::organizationId(),
        'user_id' => Fixtures::userId(),
        'student_code' => 'STU-RS-'.str()->random(4),
    ]);

    app(ArchiveStudentAction::class)->execute($student, 'خطأ إداري');
    Event::fake([StudentRestored::class]);

    $restored = app(RestoreStudentAction::class)->execute((string) $student->getKey());

    expect($restored->trashed())->toBeFalse();

    Event::assertDispatched(
        StudentRestored::class,
        fn (StudentRestored $event): bool => $event->studentId === (string) $student->getKey(),
    );
});

it('refuses to restore a student who was never archived', function (): void {
    $student = StudentProfile::factory()->create([
        'organization_id' => Fixtures::organizationId(),
        'user_id' => Fixtures::userId(),
        'student_code' => 'STU-RN-'.str()->random(4),
    ]);

    app(RestoreStudentAction::class)->execute((string) $student->getKey());
})->throws(BusinessRuleViolation::class);

it('fails clearly for an unknown student id', function (): void {
    app(RestoreStudentAction::class)->execute((string) str()->ulid());
})->throws(BusinessRuleViolation::class);
