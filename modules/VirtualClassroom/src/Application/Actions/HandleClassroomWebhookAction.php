<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantLeft;
use Modules\VirtualClassroom\Domain\Events\ClassroomStarted;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\Models\ClassroomEvent;

final readonly class HandleClassroomWebhookAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private Dispatcher $events,
    ) {}

    public function execute(Request $request): ?ClassroomEvent
    {
        $webhook = $this->provider->parseWebhook($request);

        if ($webhook === null) {
            return null;
        }

        /** @var Classroom|null $classroom */
        $classroom = Classroom::query()->where('external_id', $webhook->externalId)->first();

        if ($classroom === null) {
            return null;
        }

        $providerEventId = data_get($webhook->payload, 'data.id')
            ?? data_get($webhook->payload, 'header.event.id');
        $idempotencyKey = hash('sha256', implode('|', [
            $this->provider->name(),
            $webhook->externalId,
            $webhook->type->value,
            is_scalar($providerEventId) ? (string) $providerEventId : '',
            $webhook->externalUserId ?? '',
            $webhook->occurredAt->format('U.u'),
        ]));

        return DB::transaction(function () use ($webhook, $classroom, $idempotencyKey): ClassroomEvent {
            $classroomEvent = ClassroomEvent::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'classroom_id' => $classroom->id,
                    'event_type' => $webhook->type,
                    'external_user_id' => $webhook->externalUserId,
                    'occurred_at' => $webhook->occurredAt,
                    'payload' => $webhook->payload,
                ],
            );

            if (!$classroomEvent->wasRecentlyCreated) {
                return $classroomEvent;
            }

            if ($webhook->type === ClassroomEventType::MeetingStarted) {
                $classroom->forceFill(['started_at' => $webhook->occurredAt])->save();
            }

            if ($webhook->type === ClassroomEventType::MeetingEnded) {
                $classroom->forceFill([
                    'ended_at' => $webhook->occurredAt,
                    'max_concurrent_participants' => (int) data_get(
                        $webhook->payload,
                        'data.attributes.meeting.max-concurrent-users',
                        0,
                    ),
                ])->save();
            }

            $joinRole = strtoupper((string) data_get($webhook->payload, 'data.attributes.user.role')) === 'MODERATOR'
                ? JoinRole::Moderator
                : JoinRole::Attendee;

            match ($webhook->type) {
                ClassroomEventType::MeetingStarted => $this->events->dispatch(new ClassroomStarted(
                    classroomId: (string) $classroom->id,
                    sessionId: (string) $classroom->session_id,
                    provider: (string) $classroom->provider,
                    startedAt: $webhook->occurredAt->toIso8601String(),
                )),
                ClassroomEventType::ParticipantJoined => $this->events->dispatch(new ClassroomParticipantJoined(
                    classroomId: (string) $classroom->id,
                    sessionId: (string) $classroom->session_id,
                    provider: (string) $classroom->provider,
                    externalUserId: (string) $webhook->externalUserId,
                    userId: $webhook->externalUserId,
                    role: $joinRole,
                    occurredAt: $webhook->occurredAt->toIso8601String(),
                )),
                ClassroomEventType::ParticipantLeft => $this->events->dispatch(new ClassroomParticipantLeft(
                    classroomId: (string) $classroom->id,
                    sessionId: (string) $classroom->session_id,
                    provider: (string) $classroom->provider,
                    externalUserId: (string) $webhook->externalUserId,
                    userId: $webhook->externalUserId,
                    occurredAt: $webhook->occurredAt->toIso8601String(),
                )),
                ClassroomEventType::MeetingEnded => $this->events->dispatch(new ClassroomEnded(
                    classroomId: (string) $classroom->id,
                    sessionId: (string) $classroom->session_id,
                    provider: (string) $classroom->provider,
                    endedAt: $webhook->occurredAt->toIso8601String(),
                    maxConcurrentParticipants: (int) data_get($webhook->payload, 'data.attributes.meeting.max-concurrent-users', 0),
                )),
                default => null,
            };

            return $classroomEvent;
        });
    }
}
