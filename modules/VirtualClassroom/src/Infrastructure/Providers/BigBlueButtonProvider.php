<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Infrastructure\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\VirtualClassroom\Domain\Contracts\SupportsWebhookRegistration;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomHealth;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Modules\VirtualClassroom\Domain\ValueObjects\ParticipantSnapshot;
use Modules\VirtualClassroom\Domain\ValueObjects\RecordingHandle;
use Modules\VirtualClassroom\Domain\ValueObjects\RegisteredWebhook;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;
use Modules\VirtualClassroom\Domain\ValueObjects\WebhookEvent;
use SimpleXMLElement;
use Throwable;

/**
 * محوّل BigBlueButton الرسمي. لا يسرّب XML أو استثناءات HTTP خارج الموديول.
 */
final class BigBlueButtonProvider implements SupportsWebhookRegistration, VirtualClassroomProvider
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        private readonly array $configuration,
    ) {}

    public function name(): string
    {
        return 'bigbluebutton';
    }

    public function createClassroom(ClassroomSpec $spec): RemoteClassroom
    {
        $moderatorSecret = bin2hex(random_bytes(16));
        $attendeeSecret = bin2hex(random_bytes(16));

        $response = $this->call('create', [
            'name' => $spec->title,
            'meetingID' => $spec->externalMeetingId,
            'moderatorPW' => $moderatorSecret,
            'attendeePW' => $attendeeSecret,
            'record' => $spec->recordable ? 'true' : 'false',
            'autoStartRecording' => $spec->recordable ? 'true' : 'false',
            'allowStartStopRecording' => $spec->recordable ? 'true' : 'false',
            'maxParticipants' => (string) max(0, $spec->maxParticipants),
        ]);
        $this->assertSuccess($response, 'create');

        return new RemoteClassroom(
            externalId: $this->xmlValue($response, 'meetingID') ?? $spec->externalMeetingId,
            moderatorSecret: $this->xmlValue($response, 'moderatorPW') ?? $moderatorSecret,
            attendeeSecret: $this->xmlValue($response, 'attendeePW') ?? $attendeeSecret,
            createdAt: $this->timestamp($this->xmlValue($response, 'createTime'))
                ?? CarbonImmutable::now('UTC'),
            meta: [
                'internal_meeting_id' => $this->xmlValue($response, 'internalMeetingID'),
                'create_time' => $this->xmlValue($response, 'createTime'),
                'recordable' => $spec->recordable,
            ],
        );
    }

    public function generateJoinUrl(JoinRequest $request): string
    {
        $params = [
            'meetingID' => $request->externalId,
            'fullName' => $request->displayName,
            'role' => strtoupper($request->role->value),
            'password' => $request->rolePassword,
            'joinViaHtml5' => 'true',
            'redirect' => 'true',
        ];

        if ($request->externalUserId !== null && $request->externalUserId !== '') {
            $params['userID'] = $request->externalUserId;
        }

        return $this->buildUrl('join', $params);
    }

    public function isRunning(string $externalId): bool
    {
        $response = $this->call('isMeetingRunning', ['meetingID' => $externalId]);
        $this->assertSuccess($response, 'isMeetingRunning');

        return filter_var($this->xmlValue($response, 'running'), FILTER_VALIDATE_BOOL);
    }

    public function participants(string $externalId): array
    {
        $response = $this->call('getMeetingInfo', ['meetingID' => $externalId]);
        $this->assertSuccess($response, 'getMeetingInfo');
        $participants = [];

        foreach ($response->attendees->attendee ?? [] as $attendee) {
            $participants[] = new ParticipantSnapshot(
                externalUserId: (string) ($attendee->userID ?? ''),
                fullName: (string) ($attendee->fullName ?? ''),
                role: strtoupper((string) ($attendee->role ?? '')) === 'MODERATOR'
                    ? JoinRole::Moderator
                    : JoinRole::Viewer,
                joinedAt: $this->timestamp(isset($attendee->joinTime) ? (string) $attendee->joinTime : null),
            );
        }

        return $participants;
    }

    public function endClassroom(string $externalId, ?string $moderatorSecret = null): void
    {
        $parameters = ['meetingID' => $externalId];

        if ($moderatorSecret !== null && $moderatorSecret !== '') {
            $parameters['password'] = $moderatorSecret;
        }

        $response = $this->call('end', $parameters);

        if (!$this->isNotFound($response)) {
            $this->assertSuccess($response, 'end');
        }
    }

    public function startRecording(string $externalId): void
    {
        throw ClassroomProviderException::unsupported([
            'capability' => __('virtualclassroom::errors.capability_runtime_recording_control'),
        ]);
    }

    public function pauseRecording(string $externalId): void
    {
        throw ClassroomProviderException::unsupported([
            'capability' => __('virtualclassroom::errors.capability_runtime_recording_control'),
        ]);
    }

    public function recordings(string $externalId): array
    {
        $response = $this->call('getRecordings', ['meetingID' => $externalId]);
        $this->assertSuccess($response, 'getRecordings');
        $recordings = [];

        foreach ($response->recordings->recording ?? [] as $recording) {
            $formats = [];

            foreach ($recording->playback->format ?? [] as $format) {
                $formats[] = [
                    'type' => (string) ($format->type ?? ''),
                    'url' => (string) ($format->url ?? ''),
                    'length' => (int) ($format->length ?? 0),
                ];
            }

            $recordings[] = new RecordingHandle(
                recordingId: (string) ($recording->recordID ?? ''),
                externalId: (string) ($recording->meetingID ?? $externalId),
                startedAt: $this->timestamp(isset($recording->startTime) ? (string) $recording->startTime : null),
                endedAt: $this->timestamp(isset($recording->endTime) ? (string) $recording->endTime : null),
                formats: $formats,
            );
        }

        return $recordings;
    }

    public function deleteRecording(string $recordingId): void
    {
        $response = $this->call('deleteRecordings', ['recordID' => $recordingId]);

        if (!$this->isNotFound($response)) {
            $this->assertSuccess($response, 'deleteRecordings');
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $rawBody = (string) $request->getContent();

        if ($rawBody === '') {
            $rawBody = http_build_query($request->request->all(), '', '&', PHP_QUERY_RFC3986);
        }

        if (!$this->webhookSignatureIsValid($request, $rawBody)) {
            throw ClassroomProviderException::invalidWebhookSignature();
        }

        $encodedEvent = $request->request->get('event');

        if (!is_string($encodedEvent) || $encodedEvent === '') {
            return null;
        }

        $payload = json_decode($encodedEvent, true);

        if (!is_array($payload)) {
            return null;
        }

        $eventName = data_get($payload, 'data.id') ?? data_get($payload, 'header.event.name');

        if (!is_string($eventName)) {
            return null;
        }

        $type = match (strtolower(str_replace('_', '-', $eventName))) {
            'meeting-created', 'meeting-started' => ClassroomEventType::MeetingStarted,
            'meeting-ended', 'meeting-destroyed' => ClassroomEventType::MeetingEnded,
            'user-joined' => ClassroomEventType::ParticipantJoined,
            'user-left' => ClassroomEventType::ParticipantLeft,
            'recording-started' => ClassroomEventType::RecordingStarted,
            'recording-paused', 'recording-stopped' => ClassroomEventType::RecordingPaused,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $externalId = data_get($payload, 'data.attributes.meeting.external-meeting-id')
            ?? data_get($payload, 'data.attributes.meeting.externalMeetingId')
            ?? data_get($payload, 'header.meeting_id');

        if (!is_string($externalId) || $externalId === '') {
            return null;
        }

        $externalUserId = data_get($payload, 'data.attributes.user.external-user-id')
            ?? data_get($payload, 'data.attributes.user.externalUserId')
            ?? data_get($payload, 'header.user_id');
        $occurredAt = data_get($payload, 'data.event.ts')
            ?? data_get($payload, 'header.event.timestamp_ts')
            ?? data_get($payload, 'header.event.timestamp')
            ?? $request->request->get('timestamp');

        return new WebhookEvent(
            type: $type,
            externalId: $externalId,
            externalUserId: is_scalar($externalUserId) ? (string) $externalUserId : null,
            occurredAt: $this->timestamp(is_scalar($occurredAt) ? (string) $occurredAt : null)
                ?? CarbonImmutable::now('UTC'),
            payload: $payload,
        );
    }

    public function registerWebhook(string $callbackUrl, ?string $externalId = null): RegisteredWebhook
    {
        $params = ['callbackURL' => $callbackUrl];

        if ($externalId !== null && $externalId !== '') {
            $params['meetingID'] = $externalId;
        }

        $response = $this->call('hooks/create', $params);
        $this->assertSuccess($response, 'hooks/create');
        $hookId = $this->xmlValue($response, 'hookID');

        if ($hookId === null) {
            throw ClassroomProviderException::rejected([
                'action' => 'hooks/create',
                'code' => 'missing_hook_id',
            ]);
        }

        return new RegisteredWebhook(
            hookId: $hookId,
            callbackUrl: $callbackUrl,
            externalId: $externalId,
            permanent: filter_var($this->xmlValue($response, 'permanentHook'), FILTER_VALIDATE_BOOL),
        );
    }

    public function registeredWebhooks(?string $externalId = null): array
    {
        $params = [];

        if ($externalId !== null && $externalId !== '') {
            $params['meetingID'] = $externalId;
        }

        $response = $this->call('hooks/list', $params);
        $this->assertSuccess($response, 'hooks/list');
        $hooks = [];

        foreach ($response->hooks->hook ?? [] as $hook) {
            $meetingId = trim((string) ($hook->meetingID ?? ''));

            $hooks[] = new RegisteredWebhook(
                hookId: trim((string) ($hook->hookID ?? '')),
                callbackUrl: trim((string) ($hook->callbackURL ?? '')),
                externalId: $meetingId === '' ? null : $meetingId,
                permanent: filter_var((string) ($hook->permanentHook ?? ''), FILTER_VALIDATE_BOOL),
            );
        }

        return $hooks;
    }

    public function removeWebhook(string $hookId): void
    {
        $response = $this->call('hooks/destroy', ['hookID' => $hookId]);

        if (!$this->isNotFound($response)) {
            $this->assertSuccess($response, 'hooks/destroy');
        }
    }

    public function healthCheck(): ClassroomHealth
    {
        try {
            $response = $this->call('getMeetings', []);
            $this->assertSuccess($response, 'getMeetings');

            return new ClassroomHealth(ClassroomHealthStatus::Healthy);
        } catch (ClassroomProviderException $exception) {
            Log::warning('virtualclassroom.provider_health_check_failed', [
                'provider' => $this->name(),
                'reason' => $exception->reason,
            ]);

            return new ClassroomHealth(ClassroomHealthStatus::Down, $exception->getMessage());
        }
    }

    public function capabilities(): array
    {
        $supports = (array) ($this->configuration['supports'] ?? []);

        return array_map(static fn (mixed $value): bool => (bool) $value, $supports);
    }

    /** @param array<string, string> $params */
    private function call(string $action, array $params): SimpleXMLElement
    {
        $this->ensureConfigured();
        $this->ensureCircuitClosed();
        $retryDelays = array_values(array_map(
            static fn (mixed $delay): int => max(0, (int) $delay),
            (array) ($this->configuration['retry_delays_milliseconds'] ?? []),
        ));

        try {
            $response = Http::accept('application/xml')
                ->timeout((int) $this->configuration['timeout_seconds'])
                ->connectTimeout((int) $this->configuration['connect_timeout_seconds'])
                ->retry(
                    count($retryDelays) + 1,
                    static fn (int $attempt): int => $retryDelays[$attempt - 1]
                        ?? ($retryDelays === [] ? 0 : $retryDelays[array_key_last($retryDelays)]),
                    fn (Throwable $exception): bool => $this->isTransient($exception),
                    throw: false,
                )
                ->get($this->buildUrl($action, $params));
        } catch (Throwable $exception) {
            if ($this->isTransient($exception)) {
                $this->recordTransientFailure();

                throw ClassroomProviderException::unavailable(['action' => $action], $exception);
            }

            throw ClassroomProviderException::rejected(['action' => $action]);
        }

        if ($this->isTransientResponse($response)) {
            $this->recordTransientFailure();

            throw ClassroomProviderException::unavailable([
                'action' => $action,
                'status' => $response->status(),
            ]);
        }

        if ($response->failed()) {
            throw ClassroomProviderException::rejected([
                'action' => $action,
                'status' => $response->status(),
            ]);
        }

        $xml = $this->parseXml($response->body());

        if ($xml === null) {
            $this->recordTransientFailure();

            throw ClassroomProviderException::unavailable(['action' => $action]);
        }

        $this->recordSuccess();

        return $xml;
    }

    /** @param array<string, string> $params */
    private function buildUrl(string $action, array $params): string
    {
        $baseUrl = rtrim((string) ($this->configuration['base_url'] ?? ''), '/');

        if (!str_ends_with($baseUrl, '/api')) {
            $baseUrl .= '/api';
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $checksum = $this->checksum($action.$query.(string) ($this->configuration['secret'] ?? ''));

        return $baseUrl.'/'.$action.'?'.$query
            .($query === '' ? '' : '&')
            .'checksum='.$checksum;
    }

    private function ensureConfigured(): void
    {
        $baseUrl = $this->configuration['base_url'] ?? null;
        $secret = $this->configuration['secret'] ?? null;
        $timeout = (int) ($this->configuration['timeout_seconds'] ?? 0);
        $connectTimeout = (int) ($this->configuration['connect_timeout_seconds'] ?? 0);

        if (!is_string($baseUrl) || $baseUrl === ''
            || !is_string($secret) || $secret === ''
            || $timeout <= 0 || $connectTimeout <= 0) {
            throw ClassroomProviderException::configuration(['provider' => $this->name()]);
        }
    }

    private function assertSuccess(SimpleXMLElement $response, string $action): void
    {
        if (strtoupper((string) ($response->returncode ?? '')) !== 'SUCCESS') {
            throw ClassroomProviderException::rejected([
                'action' => $action,
                'code' => $this->xmlValue($response, 'messageKey') ?? 'unknown',
            ]);
        }
    }

    private function isNotFound(SimpleXMLElement $response): bool
    {
        $messageKey = strtolower($this->xmlValue($response, 'messageKey') ?? '');

        return str_contains($messageKey, 'notfound') || str_contains($messageKey, 'not-found');
    }

    private function parseXml(string $body): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function webhookSignatureIsValid(Request $request, string $rawBody): bool
    {
        $provided = $request->query('checksum');
        $secret = $this->configuration['webhook_secret'] ?? null;
        $callbackUrl = $this->configuration['webhook_callback_url'] ?? null;

        if (!is_string($secret) || $secret === '') {
            $secret = $this->configuration['secret'] ?? null;
        }

        if (!is_string($provided) || $provided === '' || !is_string($secret) || $secret === '') {
            return false;
        }

        if (!is_string($callbackUrl) || $callbackUrl === '') {
            $callbackUrl = $request->url();
        }

        return hash_equals($this->checksum($callbackUrl.$rawBody.$secret), strtolower($provided));
    }

    private function checksum(string $value): string
    {
        $algorithm = strtolower((string) ($this->configuration['checksum_algorithm'] ?? 'sha1'));

        if (!in_array($algorithm, hash_algos(), true)) {
            throw ClassroomProviderException::configuration([
                'provider' => $this->name(),
                'reason' => 'unsupported_checksum_algorithm',
            ]);
        }

        return hash($algorithm, $value);
    }

    private function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response !== null
            && $this->isTransientResponse($exception->response);
    }

    private function isTransientResponse(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function ensureCircuitClosed(): void
    {
        $openUntil = Cache::get($this->circuitKey('open_until'));

        if (is_numeric($openUntil) && (int) $openUntil > CarbonImmutable::now('UTC')->getTimestamp()) {
            throw ClassroomProviderException::unavailable(['reason' => 'circuit_open']);
        }

        if (is_numeric($openUntil)) {
            Cache::forget($this->circuitKey('open_until'));
        }
    }

    private function recordTransientFailure(): void
    {
        $threshold = max(1, (int) data_get($this->configuration, 'circuit_breaker.failure_threshold'));
        $openSeconds = max(1, (int) data_get($this->configuration, 'circuit_breaker.open_seconds'));
        $failureKey = $this->circuitKey('failures');
        $failures = (int) Cache::get($failureKey, 0) + 1;

        Cache::put($failureKey, $failures, $openSeconds);

        if ($failures >= $threshold) {
            Cache::put(
                $this->circuitKey('open_until'),
                CarbonImmutable::now('UTC')->addSeconds($openSeconds)->getTimestamp(),
                $openSeconds,
            );
        }
    }

    private function recordSuccess(): void
    {
        Cache::forget($this->circuitKey('failures'));
        Cache::forget($this->circuitKey('open_until'));
    }

    private function circuitKey(string $suffix): string
    {
        return 'virtual_classroom:bbb:'.sha1((string) ($this->configuration['base_url'] ?? '')).':'.$suffix;
    }

    private function timestamp(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $timestamp = (int) $value;

        if ($timestamp > 9999999999) {
            $timestamp = intdiv($timestamp, 1000);
        }

        return CarbonImmutable::createFromTimestampUTC($timestamp);
    }

    private function xmlValue(SimpleXMLElement $xml, string $tag): ?string
    {
        $value = isset($xml->{$tag}) ? trim((string) $xml->{$tag}) : '';

        return $value === '' ? null : $value;
    }
}
