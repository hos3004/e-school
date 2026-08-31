<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Mockery\MockInterface;
use Modules\Attendance\Application\Policies\AttendancePolicy;
use Modules\Attendance\Database\Factories\AttendanceFactory;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Support\ApiUser;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;

function attendancePolicyUser(bool $granted, string $organizationId): ApiUser
{
    foreach (['view', 'record', 'override'] as $ability) {
        Gate::define("attendance.{$ability}", fn (): bool => $granted);
    }

    return new ApiUser((string) str()->ulid(), $organizationId);
}

it('combines declared permissions with organization ownership', function (): void {
    $organizationId = (string) str()->ulid();
    $participantId = (string) str()->ulid();
    $participant = new SessionParticipantAdministrationData(
        id: $participantId,
        organizationId: $organizationId,
        sessionId: (string) str()->ulid(),
        studentProfileId: (string) str()->ulid(),
        enrollmentId: (string) str()->ulid(),
        courseId: (string) str()->ulid(),
        groupId: null,
        staffProfileId: (string) str()->ulid(),
        sessionTitle: ['en' => 'Session'],
        sessionStatus: 'scheduled',
        scheduledStart: now()->toIso8601String(),
        scheduledEnd: now()->addHour()->toIso8601String(),
        firstJoinedAt: null,
        lastLeftAt: null,
        attendedMinutes: 0,
        invitationActive: true,
    );
    /** @var SessionParticipantAdministrationQueries&MockInterface $queries */
    $queries = Mockery::mock(SessionParticipantAdministrationQueries::class);
    $queries->shouldReceive('findForOrganization')
        ->with($organizationId, $participantId)
        ->andReturn($participant);
    $policy = new AttendancePolicy($queries);
    /** @var Attendance $record */
    $record = AttendanceFactory::new()->make(['session_participant_id' => $participantId]);

    expect($policy->viewAny(attendancePolicyUser(true, $organizationId)))->toBeTrue()
        ->and($policy->view(attendancePolicyUser(true, $organizationId), $record))->toBeTrue()
        ->and($policy->create(attendancePolicyUser(true, $organizationId)))->toBeTrue()
        ->and($policy->update(attendancePolicyUser(true, $organizationId), $record))->toBeFalse()
        ->and($policy->delete(attendancePolicyUser(true, $organizationId), $record))->toBeFalse()
        ->and($policy->confirm(attendancePolicyUser(true, $organizationId), $record))->toBeTrue()
        ->and($policy->override(attendancePolicyUser(true, $organizationId), $record))->toBeTrue();

    expect($policy->viewAny(attendancePolicyUser(false, $organizationId)))->toBeFalse()
        ->and($policy->view(attendancePolicyUser(false, $organizationId), $record))->toBeFalse()
        ->and($policy->create(attendancePolicyUser(false, $organizationId)))->toBeFalse()
        ->and($policy->confirm(attendancePolicyUser(false, $organizationId), $record))->toBeFalse()
        ->and($policy->override(attendancePolicyUser(false, $organizationId), $record))->toBeFalse();
});

it('never inspects role names', function (): void {
    $source = (string) file_get_contents((new ReflectionClass(AttendancePolicy::class))->getFileName());

    expect(str_contains($source, 'hasRole'))->toBeFalse()
        ->and(str_contains($source, 'role ==='))->toBeFalse();
});
