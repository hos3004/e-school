<?php

declare(strict_types=1);

use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Modules\VirtualClassroom\Infrastructure\Providers\NullProvider;

it('simulates the classroom lifecycle without network access', function (): void {
    $provider = new NullProvider;
    $remote = $provider->createClassroom(new ClassroomSpec(
        sessionId: 'session-1',
        externalMeetingId: 'meeting-1',
        title: 'Test classroom',
        startsAt: null,
        maxParticipants: 25,
        recordable: true,
    ));

    expect($remote->externalId)->toBe('meeting-1')
        ->and($provider->isRunning('meeting-1'))->toBeFalse();

    $joinUrl = $provider->generateJoinUrl(new JoinRequest(
        externalId: 'meeting-1',
        displayName: 'Test Student',
        role: JoinRole::Viewer,
        rolePassword: 'unused-by-null-provider',
        externalUserId: 'student-1',
    ));

    expect($joinUrl)->toStartWith('https://virtual-classroom.test/join?')
        ->and($provider->isRunning('meeting-1'))->toBeTrue()
        ->and($provider->participants('meeting-1'))->toHaveCount(1)
        ->and($provider->participants('meeting-1')[0]->externalUserId)->toBe('student-1');

    $provider->startRecording('meeting-1');
    $provider->pauseRecording('meeting-1');
    $provider->endClassroom('meeting-1');

    expect($provider->isRunning('meeting-1'))->toBeFalse()
        ->and($provider->participants('meeting-1'))->toBe([])
        ->and($provider->healthCheck()->status->value)->toBe('healthy');
});

it('is resolved from the configured provider binding', function (): void {
    config(['virtual-classroom.default' => 'null']);
    app()->forgetInstance(VirtualClassroomProvider::class);

    expect(app(VirtualClassroomProvider::class))->toBeInstanceOf(NullProvider::class);
});
