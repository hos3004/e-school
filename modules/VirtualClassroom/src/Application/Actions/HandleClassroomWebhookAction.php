<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
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
        private SessionAdministrationQueries $sessions,
        private UserAccountDirectory $accounts,
        private AuditRecorder $audit,
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

        $organizationId = $this->sessions->organizationIdForSession((string) $classroom->session_id);
        if ($organizationId === null) {
            return null;
        }
        $userId = $webhook->externalUserId === null
            ? null
            : ($this->accounts->find($organizationId, $webhook->externalUserId)?->id);

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

        return DB::transaction(function () use (
            $webhook,
            $classroom,
            $idempotencyKey,
            $organizationId,
            $userId,
        ): ClassroomEvent {
            $classroomEvent = ClassroomEvent::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'classroom_id' => $classroom->id,
                    'event_type' => $webhook->type,
                    'external_user_id' => $webhook->externalUserId,
                    'user_id' => $userId,
                    'occurred_at' => $webhook->occurredAt,
                    'payload' => $webhook->payload,
                ],
            );

            if (!$classroomEvent->wasRecentlyCreated) {
                return $classroomEvent;
            }

            if ($webhook->type === ClassroomEventType::MeetingStarted) {
                $classroom->forceFill([
                    'status' => ClassroomStatus::Running,
                    'started_at' => $webhook->occurredAt,
                ])->save();
                $this->auditLifecycle(
                    $classroom,
                    $organizationId,
                    ClassroomStatus::Provisioned,
                    ClassroomStatus::Running,
                    __('virtualclassroom::messages.webhook_started_reason'),
                );
            }

            if ($webhook->type === ClassroomEventType::MeetingEnded) {
                $classroom->forceFill([
                    'status' => ClassroomStatus::Ended,
                    'ended_at' => $webhook->occurredAt,
                    'max_concurrent_participants' => (int) data_get(
                        $webhook->payload,
                        'data.attributes.meeting.max-concurrent-users',
                        0,
                    ),
                ])->save();
                $this->auditLifecycle(
                    $classroom,
                    $organizationId,
                    $classroom->started_at === null ? ClassroomStatus::Provisioned : ClassroomStatus::Running,
                    ClassroomStatus::Ended,
                    __('virtualclassroom::messages.webhook_ended_reason'),
                );
            }

            $joinRole = strtoupper((string) data_get($webhook->payload, 'data.attributes.user.role')) === 'MODERATOR'
                ? JoinRole::Moderator
                : JoinRole::Viewer;

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
                    userId: $userId,
                    role: $joinRole,
                    occurredAt: $webhook->occurredAt->toIso8601String(),
                )),
                ClassroomEventType::ParticipantLeft => $this->events->dispatch(new ClassroomParticipantLeft(
                    classroomId: (string) $classroom->id,
                    sessionId: (string) $classroom->session_id,
                    provider: (string) $classroom->provider,
                    externalUserId: (string) $webhook->externalUserId,
                    userId: $userId,
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

    private function auditLifecycle(
        Classroom $classroom,
        string $organizationId,
        ClassroomStatus $from,
        ClassroomStatus $to,
        string $reason,
    ): void {
        $this->audit->record(
            organizationId: $organizationId,
            actorId: null,
            actorType: 'integration',
            action: 'virtualclassroom.'.$to->value,
            auditableType: 'classrooms',
            auditableId: (string) $classroom->getKey(),
            oldValues: ['status' => $from->value],
            newValues: ['status' => $to->value],
            reason: $reason,
        );
    }
}
