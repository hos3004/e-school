<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomPresenceQueries;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\Models\ClassroomEvent;
use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

const BBB_ATTENDANCE_CALLBACK_URL = 'https://eschool.test/api/webhooks/classroom';

beforeEach(function (): void {
    config([
        'virtual-classroom.default' => 'bigbluebutton',
        'virtual-classroom.providers.bigbluebutton' => [
            'driver' => BigBlueButtonProvider::class,
            'base_url' => 'https://bbb.test/bigbluebutton/',
            'secret' => 'api-secret',
            'webhook_secret' => 'webhook-secret',
            'webhook_callback_url' => BBB_ATTENDANCE_CALLBACK_URL,
            'timeout_seconds' => 10,
            'connect_timeout_seconds' => 5,
            'retry_delays_milliseconds' => [],
            'circuit_breaker' => ['failure_threshold' => 2, 'open_seconds' => 120],
            'supports' => [],
        ],
    ]);
    app()->forgetInstance(VirtualClassroomProvider::class);
});

/**
 * يرسل التسليم كما يبنيه bbb-webhooks 3 حرفيًا: domain ثم الحدث ملفوفًا
 * في مصفوفة ثم timestamp، والتوقيع على الرابط + الجسم الخام + السر.
 */
function deliverBbbWebhook(object $test, string $eventId, string $meetingId, string $userId, CarbonImmutable $at): void
{
    $event = json_encode([
        'data' => [
            'type' => 'event',
            'id' => $eventId,
            'attributes' => [
                'meeting' => ['internal-meeting-id' => 'internal-1', 'external-meeting-id' => $meetingId],
                'user' => ['internal-user-id' => 'w_1', 'external-user-id' => $userId, 'role' => 'MODERATOR'],
            ],
            'event' => ['ts' => $at->getTimestampMs()],
        ],
    ], JSON_THROW_ON_ERROR);
    $parameters = [
        'domain' => 'bbb.telecourse.org',
        'event' => '['.$event.']',
        'timestamp' => (string) $at->getTimestampMs(),
    ];
    $body = http_build_query($parameters);
    $checksum = sha1(BBB_ATTENDANCE_CALLBACK_URL.$body.'webhook-secret');

    $test->call(
        'POST',
        '/api/webhooks/classroom?checksum='.$checksum,
        $parameters,
        server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        content: $body,
    )->assertNoContent();
}

it('turns real bbb-webhooks 3 deliveries into teacher presence', function (): void {
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $session = DB::table('sessions')->where('id', $sessionId)->first();
    $teacherUserId = (string) DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');
    $startsAt = CarbonImmutable::parse((string) $session->scheduled_start, 'UTC');
    $endsAt = CarbonImmutable::parse((string) $session->scheduled_end, 'UTC');
    $meetingId = 'SES-'.$sessionId.'-R2';
    Classroom::query()->create([
        'session_id' => $sessionId,
        'provider' => 'bigbluebutton',
        'external_id' => $meetingId,
        'moderator_secret' => 'modsec123',
        'attendee_secret' => 'attsec123',
        'created_remote_at' => $startsAt->subMinutes(20),
        'status' => 'provisioned',
    ]);
    $presence = app(ClassroomPresenceQueries::class);

    expect($presence->wasUserPresent($sessionId, $teacherUserId, $startsAt, $endsAt))->toBeFalse();

    deliverBbbWebhook($this, 'user-joined', $meetingId, $teacherUserId, $startsAt->addMinute());
    deliverBbbWebhook($this, 'user-left', $meetingId, $teacherUserId, $startsAt->addMinutes(20));
    // BBB يعيد المحاولة؛ التسليم المكرر لا يضاعف الأحداث.
    deliverBbbWebhook($this, 'user-joined', $meetingId, $teacherUserId, $startsAt->addMinute());

    expect(ClassroomEvent::query()->count())->toBe(2)
        ->and(ClassroomEvent::query()
            ->where('event_type', ClassroomEventType::ParticipantJoined->value)
            ->value('user_id'))->toBe($teacherUserId)
        ->and($presence->wasUserPresent($sessionId, $teacherUserId, $startsAt, $endsAt))->toBeTrue();
});
