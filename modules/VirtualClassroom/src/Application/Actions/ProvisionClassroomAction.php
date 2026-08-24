<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Events\ClassroomProvisioned;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;

final readonly class ProvisionClassroomAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private Dispatcher $events,
    ) {}

    public function execute(
        string $sessionId,
        string $title,
        ?int $maxParticipants = null,
        ?bool $recordable = null,
    ): Classroom {
        /** @var Classroom|null $existing */
        $existing = Classroom::query()->where('session_id', $sessionId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $maxParticipants ??= (int) config('virtual-classroom.capacity.max_participants_group');
        $recordable ??= (bool) config('virtual-classroom.recording.auto_record', true);

        $externalMeetingId = 'SES-'.$sessionId;
        $spec = new ClassroomSpec(
            sessionId: $sessionId,
            title: $title,
            externalMeetingId: $externalMeetingId,
            startsAt: null,
            maxParticipants: $maxParticipants,
            recordable: $recordable,
        );

        $remote = $this->provider->createClassroom($spec);

        return DB::transaction(function () use ($sessionId, $remote): Classroom {
            $classroom = Classroom::query()->create([
                'session_id' => $sessionId,
                'provider' => $this->provider->name(),
                'external_id' => $remote->externalId,
                'moderator_secret' => $remote->moderatorSecret,
                'attendee_secret' => $remote->attendeeSecret,
                'external_meta' => $remote->meta,
                'created_remote_at' => $remote->createdAt,
            ]);

            $this->events->dispatch(new ClassroomProvisioned(
                classroomId: (string) $classroom->id,
                sessionId: $sessionId,
                provider: $this->provider->name(),
                externalId: $remote->externalId,
                createdRemoteAt: $remote->createdAt->toIso8601String(),
            ));

            return $classroom;
        });
    }
}
