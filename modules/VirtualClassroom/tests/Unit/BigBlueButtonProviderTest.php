<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;

/** @return array<string, mixed> */
function bbbProviderTestConfiguration(): array
{
    return [
        'base_url' => 'https://bbb.test/bigbluebutton/',
        'secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'webhook_callback_url' => 'https://eschool.test/webhooks/bbb',
        'timeout_seconds' => 10,
        'connect_timeout_seconds' => 5,
        'retry_delays_milliseconds' => [],
        'circuit_breaker' => [
            'failure_threshold' => 2,
            'open_seconds' => 120,
        ],
        'supports' => [
            'recording' => true,
            'runtime_recording_control' => false,
        ],
    ];
}

beforeEach(function (): void {
    Cache::flush();
});

it('creates a meeting and signs the exact transmitted query', function (): void {
    Http::fake([
        'bbb.test/*' => Http::response(<<<'XML'
            <response>
                <returncode>SUCCESS</returncode>
                <meetingID>meeting-1</meetingID>
                <internalMeetingID>internal-1</internalMeetingID>
                <attendeePW>viewer-secret</attendeePW>
                <moderatorPW>moderator-secret</moderatorPW>
                <createTime>1720000000000</createTime>
            </response>
            XML),
    ]);

    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());
    $remote = $provider->createClassroom(new ClassroomSpec(
        sessionId: 'session-1',
        externalMeetingId: 'meeting-1',
        title: 'Arabic lesson',
        startsAt: null,
        maxParticipants: 25,
        recordable: true,
    ));

    expect($remote->externalId)->toBe('meeting-1')
        ->and($remote->moderatorSecret)->toBe('moderator-secret')
        ->and($remote->attendeeSecret)->toBe('viewer-secret')
        ->and($remote->meta['internal_meeting_id'])->toBe('internal-1');

    Http::assertSent(function (ClientRequest $request): bool {
        $url = $request->url();
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $checksum = substr($query, (int) strrpos($query, 'checksum=') + 9);
        $unsignedQuery = substr($query, 0, (int) strrpos($query, '&checksum='));

        return str_contains($url, '/bigbluebutton/api/create?')
            && hash_equals(sha1('create'.$unsignedQuery.'api-secret'), $checksum);
    });
});

it('generates a personal signed join URL without a network request', function (): void {
    Http::fake();
    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());
    $url = $provider->generateJoinUrl(new JoinRequest(
        externalId: 'meeting-1',
        displayName: 'Teacher Name',
        role: JoinRole::Moderator,
        rolePassword: 'moderator-secret',
        externalUserId: 'teacher-1',
    ));

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['role'])->toBe('MODERATOR')
        ->and($query['password'])->toBe('moderator-secret')
        ->and($query['userID'])->toBe('teacher-1');

    Http::assertNothingSent();
});

it('converts meeting details and recordings to domain value objects', function (): void {
    Http::fake(function (ClientRequest $request) {
        return match (true) {
            str_contains($request->url(), '/getMeetingInfo?') => Http::response(<<<'XML'
                <response>
                    <returncode>SUCCESS</returncode>
                    <attendees>
                        <attendee>
                            <userID>student-1</userID>
                            <fullName>Student Name</fullName>
                            <role>VIEWER</role>
                            <joinTime>1720000000000</joinTime>
                        </attendee>
                    </attendees>
                </response>
                XML),
            str_contains($request->url(), '/getRecordings?') => Http::response(<<<'XML'
                <response>
                    <returncode>SUCCESS</returncode>
                    <recordings>
                        <recording>
                            <recordID>recording-1</recordID>
                            <meetingID>meeting-1</meetingID>
                            <startTime>1720000000000</startTime>
                            <endTime>1720003600000</endTime>
                            <playback><format><type>presentation</type><url>https://bbb.test/play</url><length>60</length></format></playback>
                        </recording>
                    </recordings>
                </response>
                XML),
            str_contains($request->url(), '/end?') => Http::response(
                '<response><returncode>SUCCESS</returncode></response>',
            ),
            str_contains($request->url(), '/deleteRecordings?') => Http::response(
                '<response><returncode>SUCCESS</returncode><deleted>true</deleted></response>',
            ),
            default => Http::response('not found', 404),
        };
    });

    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());
    $participants = $provider->participants('meeting-1');
    $recordings = $provider->recordings('meeting-1');
    $provider->endClassroom('meeting-1');
    $provider->deleteRecording('recording-1');

    expect($participants)->toHaveCount(1)
        ->and($participants[0]->externalUserId)->toBe('student-1')
        ->and($recordings)->toHaveCount(1)
        ->and($recordings[0]->recordingId)->toBe('recording-1')
        ->and($recordings[0]->formats[0]['type'])->toBe('presentation');

    Http::assertSentCount(4);
});

it('retries transient failures', function (): void {
    $configuration = bbbProviderTestConfiguration();
    $configuration['retry_delays_milliseconds'] = [0];
    $provider = new BigBlueButtonProvider($configuration);

    Http::fakeSequence()
        ->push('temporary outage', 503)
        ->push('<response><returncode>SUCCESS</returncode><running>true</running></response>', 200);

    expect($provider->isRunning('meeting-1'))->toBeTrue();
    Http::assertSentCount(2);
});

it('does not retry logical client errors', function (): void {
    Http::fake(['*' => Http::response('bad request', 400)]);
    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());

    try {
        $provider->isRunning('meeting-2');
        throw new RuntimeException('Expected ClassroomProviderException was not thrown.');
    } catch (ClassroomProviderException $exception) {
        expect($exception->reason)->toBe('rejected');
    }

    Http::assertSentCount(1);
});

it('opens the circuit after the configured number of transient failures', function (): void {
    Http::fake(['*' => Http::response('temporary outage', 503)]);
    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());

    foreach (range(1, 3) as $attempt) {
        try {
            $provider->isRunning('meeting-'.$attempt);
        } catch (ClassroomProviderException) {
            // متوقع: المحاولتان الأوليان من HTTP، والثالثة يوقفها القاطع.
        }
    }

    Http::assertSentCount(2);
});

it('verifies the official webhook checksum before parsing the event', function (): void {
    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());
    $event = json_encode([
        'data' => [
            'type' => 'event',
            'id' => 'user-joined',
            'attributes' => [
                'meeting' => ['external-meeting-id' => 'meeting-1'],
                'user' => ['external-user-id' => 'student-1'],
            ],
            'event' => ['ts' => 1720000000000],
        ],
    ], JSON_THROW_ON_ERROR);
    $parameters = ['event' => $event, 'timestamp' => '1720000000000'];
    $body = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    $checksum = sha1('https://eschool.test/webhooks/bbb'.$body.'webhook-secret');
    $request = Request::create(
        'https://eschool.test/webhooks/bbb?checksum='.$checksum,
        'POST',
        $parameters,
        content: $body,
    );

    $parsed = $provider->parseWebhook($request);

    expect($parsed?->type)->toBe(ClassroomEventType::ParticipantJoined)
        ->and($parsed?->externalId)->toBe('meeting-1')
        ->and($parsed?->externalUserId)->toBe('student-1');

    $invalid = Request::create(
        'https://eschool.test/webhooks/bbb?checksum=invalid',
        'POST',
        ['event' => '{broken-json'],
        content: 'event=%7Bbroken-json',
    );

    expect(fn () => $provider->parseWebhook($invalid))
        ->toThrow(ClassroomProviderException::class);
});

it('declares runtime recording control unsupported instead of calling a fake API', function (): void {
    Http::fake();
    $provider = new BigBlueButtonProvider(bbbProviderTestConfiguration());

    try {
        $provider->startRecording('meeting-1');
        throw new RuntimeException('Expected ClassroomProviderException was not thrown.');
    } catch (ClassroomProviderException $exception) {
        expect($exception->reason)->toBe('unsupported_capability')
            ->and($provider->capabilities()['runtime_recording_control'])->toBeFalse();
    }

    Http::assertNothingSent();
});
