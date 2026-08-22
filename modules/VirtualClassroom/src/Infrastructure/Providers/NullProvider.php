<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Infrastructure\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomHealth;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\JoinRequest;
use Modules\VirtualClassroom\Domain\ValueObjects\ParticipantSnapshot;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;
use Modules\VirtualClassroom\Domain\ValueObjects\WebhookEvent;

/**
 * مزوّد محلي حتمي للاختبارات والتطوير. لا يفتح شبكة ولا يكتب قاعدة بيانات.
 */
final class NullProvider implements VirtualClassroomProvider
{
    /** @var array<string, array{running: bool, recording: bool, participants: array<string, ParticipantSnapshot>}> */
    private array $classrooms = [];

    public function name(): string
    {
        return 'null';
    }

    public function createClassroom(ClassroomSpec $spec): RemoteClassroom
    {
        $this->classrooms[$spec->externalMeetingId] ??= [
            'running' => false,
            'recording' => $spec->recordable,
            'participants' => [],
        ];

        return new RemoteClassroom(
            externalId: $spec->externalMeetingId,
            moderatorSecret: hash('sha256', 'moderator'.$spec->externalMeetingId),
            attendeeSecret: hash('sha256', 'viewer'.$spec->externalMeetingId),
            createdAt: CarbonImmutable::now('UTC'),
            meta: ['provider' => $this->name()],
        );
    }

    public function generateJoinUrl(JoinRequest $request): string
    {
        $this->classrooms[$request->externalId] ??= [
            'running' => false,
            'recording' => false,
            'participants' => [],
        ];
        $this->classrooms[$request->externalId]['running'] = true;

        if ($request->externalUserId !== null) {
            $this->classrooms[$request->externalId]['participants'][$request->externalUserId] = new ParticipantSnapshot(
                externalUserId: $request->externalUserId,
                fullName: $request->displayName,
                role: $request->role,
                joinedAt: CarbonImmutable::now('UTC'),
            );
        }

        $token = hash('sha256', implode('|', [
            $request->externalId,
            $request->displayName,
            $request->role->value,
            $request->externalUserId ?? '',
        ]));

        return 'https://virtual-classroom.test/join?'.http_build_query([
            'meeting' => $request->externalId,
            'token' => $token,
        ]);
    }

    public function isRunning(string $externalId): bool
    {
        return $this->classrooms[$externalId]['running'] ?? false;
    }

    public function participants(string $externalId): array
    {
        return array_values($this->classrooms[$externalId]['participants'] ?? []);
    }

    public function endClassroom(string $externalId): void
    {
        if (isset($this->classrooms[$externalId])) {
            $this->classrooms[$externalId]['running'] = false;
            $this->classrooms[$externalId]['participants'] = [];
        }
    }

    public function startRecording(string $externalId): void
    {
        if (isset($this->classrooms[$externalId])) {
            $this->classrooms[$externalId]['recording'] = true;
        }
    }

    public function pauseRecording(string $externalId): void
    {
        if (isset($this->classrooms[$externalId])) {
            $this->classrooms[$externalId]['recording'] = false;
        }
    }

    public function recordings(string $externalId): array
    {
        return [];
    }

    public function deleteRecording(string $recordingId): void {}

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        return null;
    }

    public function healthCheck(): ClassroomHealth
    {
        return new ClassroomHealth(ClassroomHealthStatus::Healthy);
    }

    public function capabilities(): array
    {
        return (array) config('virtual-classroom.providers.null.supports', []);
    }
}
