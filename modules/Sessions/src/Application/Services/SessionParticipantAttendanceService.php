<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Sessions\Application\Actions\RecordParticipantAttendanceAction;
use Modules\Sessions\Domain\Contracts\SessionParticipantAttendanceGateway;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class SessionParticipantAttendanceService implements SessionParticipantAttendanceGateway
{
    public function __construct(
        private StudentDirectoryQueries $students,
        private RecordParticipantAttendanceAction $record,
    ) {}

    public function recordProviderEvent(
        string $sessionId,
        string $userId,
        string $type,
        CarbonImmutable $occurredAt,
    ): void {
        $session = Session::query()->find($sessionId);
        if ($session === null || $occurredAt->greaterThanOrEqualTo($session->scheduled_end)) {
            return;
        }

        $participants = $session->participants()->activeInvitation()->get();
        $profiles = $this->students->byIds(
            (string) $session->organization_id,
            $participants->pluck('student_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all(),
        );
        $studentProfileId = collect($profiles)
            ->first(static fn ($profile): bool => $profile->userId === $userId)
            ?->id;

        if ($studentProfileId === null) {
            return;
        }

        $participant = $participants->first(
            static fn (SessionParticipant $item): bool => (string) $item->student_profile_id === $studentProfileId,
        );
        if (!$participant instanceof SessionParticipant) {
            return;
        }

        $this->record->execute(
            session: $session,
            participant: $participant,
            type: $type,
            actorId: null,
            occurredAt: $occurredAt,
            allowReconnect: true,
        );
    }

    public function closeOpenIntervals(string $sessionId, CarbonImmutable $endedAt): void
    {
        $session = Session::query()->find($sessionId);
        if ($session === null) {
            return;
        }

        $session->participants()
            ->activeInvitation()
            ->whereNotNull('current_joined_at')
            ->get()
            ->each(function (SessionParticipant $participant) use ($session, $endedAt): void {
                $this->record->execute(
                    session: $session,
                    participant: $participant,
                    type: 'leave',
                    actorId: null,
                    occurredAt: $endedAt,
                    allowReconnect: true,
                );
            });
    }
}
