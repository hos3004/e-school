<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Application\Actions\RotateClassroomLinkAction;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;

uses(CreatesSessionParticipant::class);

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
    $context = roomReuseContext($this->createSessionParticipant());
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
    $context = roomReuseContext($this->createSessionParticipant());
    roomReuseProvider(creations: 2, available: false);

    $first = enterRoom($context, ensureRemote: false);
    // meeting-ended من BBB بعد خروج الجميع دقيقة.
    Classroom::query()->whereKey($first->id)->update(['status' => ClassroomStatus::Ended->value]);

    $reopened = enterRoom($context);

    expect($reopened->id)->toBe($first->id)
        ->and($reopened->external_id)->toBe('SES-'.$context['session_id'].'-R2')
        ->and($reopened->status)->toBe(ClassroomStatus::Provisioned);
});

/** يربط الحصة بجدول متكرر جديد، ويعيد معرّف الجدول. */
function attachPersistentSchedule(string $organizationId, string $sessionId): string
{
    $scheduleId = (string) Str::ulid();
    $session = DB::table('sessions')->where('id', $sessionId)->first(['course_id', 'staff_profile_id', 'session_type']);
    $studentProfileId = DB::table('session_participants')->where('session_id', $sessionId)->value('student_profile_id');

    DB::table('schedules')->insert([
        'id' => $scheduleId,
        'organization_id' => $organizationId,
        'student_profile_id' => $studentProfileId,
        'course_id' => $session->course_id,
        'staff_profile_id' => $session->staff_profile_id,
        'session_type' => $session->session_type,
        'rrule' => 'FREQ=WEEKLY',
        'start_time' => '10:00:00',
        'duration_minutes' => 60,
        'timezone' => 'UTC',
        'starts_on' => now()->utc()->toDateString(),
        'materialized_until' => now()->utc()->toDateString(),
        'is_active' => true,
        'created_by' => DB::table('users')->where('organization_id', $organizationId)->value('id'),
    ]);

    DB::table('sessions')->where('id', $sessionId)->update(['schedule_id' => $scheduleId]);

    return $scheduleId;
}

/** ينسخ حصة جديدة (أسبوع تالٍ أو تلافٍ) بنفس المعلم والمادة والمؤسسة. */
function cloneSessionOccurrence(string $sourceSessionId, ?string $scheduleId, ?string $makeupForSessionId): string
{
    $source = DB::table('sessions')->where('id', $sourceSessionId)->first();
    $newId = (string) Str::ulid();

    DB::table('sessions')->insert([
        'id' => $newId,
        'organization_id' => $source->organization_id,
        'schedule_id' => $scheduleId,
        'course_id' => $source->course_id,
        'staff_profile_id' => $source->staff_profile_id,
        'original_teacher_id' => $source->staff_profile_id,
        'makeup_for_session_id' => $makeupForSessionId,
        'session_type' => $makeupForSessionId === null ? 'regular' : 'makeup',
        'status' => 'confirmed',
        'scheduled_start' => CarbonImmutable::now('UTC')->addWeek(),
        'scheduled_end' => CarbonImmutable::now('UTC')->addWeek()->addHour(),
        'title' => $source->title,
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);

    return $newId;
}

it('reuses the same persistent room and external id across weekly occurrences of a schedule', function (): void {
    $participantId = $this->createSessionParticipant();
    $context = roomReuseContext($participantId);
    $scheduleId = attachPersistentSchedule($context['organization_id'], $context['session_id']);
    roomReuseProvider(creations: 1, available: true);

    $week1 = enterRoom($context);
    expect($week1->external_id)->toBe('SCH-'.$scheduleId)
        ->and($week1->room_identity)->toBe('SCH-'.$scheduleId);

    $week2SessionId = cloneSessionOccurrence($context['session_id'], $scheduleId, null);
    $week2Context = [...$context, 'session_id' => $week2SessionId];
    $week2 = enterRoom($week2Context, ensureRemote: false);

    expect($week2->id)->toBe($week1->id)
        ->and($week2->external_id)->toBe($week1->external_id)
        ->and($week2->session_id)->toBe($week2SessionId)
        ->and(Classroom::query()->count())->toBe(1);
});

it('reuses the schedule room for a makeup session standing in for a cancelled occurrence', function (): void {
    $participantId = $this->createSessionParticipant();
    $context = roomReuseContext($participantId);
    $scheduleId = attachPersistentSchedule($context['organization_id'], $context['session_id']);
    roomReuseProvider(creations: 1, available: true);

    $original = enterRoom($context);

    $makeupSessionId = cloneSessionOccurrence($context['session_id'], null, $context['session_id']);
    $makeupContext = [...$context, 'session_id' => $makeupSessionId];
    $makeup = enterRoom($makeupContext, ensureRemote: false);

    expect($makeup->id)->toBe($original->id)
        ->and($makeup->external_id)->toBe($original->external_id)
        ->and($makeup->session_id)->toBe($makeupSessionId);
});

it('lets an administrator rotate a persistent link, invalidating the previous one', function (): void {
    $participantId = $this->createSessionParticipant();
    $context = roomReuseContext($participantId);
    $scheduleId = attachPersistentSchedule($context['organization_id'], $context['session_id']);
    roomReuseProvider(creations: 2, available: true);

    $before = enterRoom($context);
    expect($before->external_id)->toBe('SCH-'.$scheduleId)
        ->and($before->link_generation)->toBe(1);

    /** @var VirtualClassroomProvider&MockInterface $provider */
    $provider = app(VirtualClassroomProvider::class);
    $provider->shouldReceive('endClassroom')->once()->with($before->external_id, $before->moderator_secret);

    $rotated = app(RotateClassroomLinkAction::class)->execute(
        organizationId: $context['organization_id'],
        scheduleId: $scheduleId,
        actorId: $context['operator_id'],
        reason: 'اشتباه في تسرب الرابط',
    );

    expect($rotated->link_generation)->toBe(2)
        ->and($rotated->external_id)->toBeNull()
        ->and($rotated->status)->toBe(ClassroomStatus::Pending);

    $after = enterRoom($context);

    expect($after->id)->toBe($before->id)
        ->and($after->external_id)->toBe('SCH-'.$scheduleId.'-G2')
        ->and($after->external_id)->not->toBe($before->external_id);
});
