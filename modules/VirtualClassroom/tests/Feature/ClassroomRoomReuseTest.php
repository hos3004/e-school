<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

/** @return array{session_id: string, organization_id: string, operator_id: string} */
function roomReuseContext(string $participantId): array
{
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $organizationId = (string) DB::table('sessions')->where('id', $sessionId)->value('organization_id');

    return [
        'session_id' => $sessionId,
        'organization_id' => $organizationId,
        'operator_id' => (string) User::query()->where('organization_id', $organizationId)->value('id'),
    ];
}

function roomReuseProvider(int $creations, bool $available): void
{
    $provider = Mockery::mock(VirtualClassroomProvider::class);
    $provider->shouldReceive('name')->andReturn('bigbluebutton');
    $provider->shouldReceive('createClassroom')
        ->times($creations)
        ->andReturnUsing(static fn (ClassroomSpec $spec): RemoteClassroom => new RemoteClassroom(
            externalId: $spec->externalMeetingId,
            moderatorSecret: 'moderator-'.$spec->externalMeetingId,
            attendeeSecret: 'attendee-'.$spec->externalMeetingId,
            createdAt: CarbonImmutable::now('UTC'),
        ));
    $provider->shouldReceive('isAvailable')->andReturn($available);
    $provider->shouldNotReceive('isRunning');
    app()->instance(VirtualClassroomProvider::class, $provider);
}

/** @param array{session_id: string, organization_id: string, operator_id: string} $context */
function enterRoom(array $context, bool $ensureRemote = true): Classroom
{
    return app(ProvisionClassroomAction::class)->execute(
        $context['session_id'],
        'فصل مشترك',
        organizationId: $context['organization_id'],
        actorId: $context['operator_id'],
        reason: 'طلب دخول من البوابة',
        ensureRemoteIsRunning: $ensureRemote,
    );
}

it('keeps everyone in one room when join links are issued before anyone enters', function (): void {
    $context = roomReuseContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    // الغرفة قائمة عند المزوّد لكن لم يدخلها أحد: كان هذا يُعيد التجهيز لكل طالب.
    roomReuseProvider(creations: 1, available: true);

    $prepared = enterRoom($context, ensureRemote: false);
    $teacher = enterRoom($context);
    $student = enterRoom($context);

    expect($teacher->external_id)->toBe($prepared->external_id)
        ->and($student->external_id)->toBe($prepared->external_id)
        ->and($student->provision_attempts)->toBe(1);
});

it('reopens a room the provider ended while the session is still joinable', function (): void {
    $context = roomReuseContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    roomReuseProvider(creations: 2, available: false);

    $first = enterRoom($context, ensureRemote: false);
    // meeting-ended من BBB بعد خروج الجميع دقيقة.
    Classroom::query()->whereKey($first->id)->update(['status' => ClassroomStatus::Ended->value]);

    $reopened = enterRoom($context);

    expect($reopened->id)->toBe($first->id)
        ->and($reopened->external_id)->toBe('SES-'.$context['session_id'].'-R2')
        ->and($reopened->status)->toBe(ClassroomStatus::Provisioned);
});
