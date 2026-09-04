<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionApproaching;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final class DispatchSessionReminders extends Command
{
    protected $signature = 'sessions:dispatch-reminders';

    protected $description = 'Queue student and teacher reminders for upcoming sessions';

    public function __construct(
        private readonly Dispatcher $events,
        private readonly StudentDirectoryQueries $students,
        private readonly StaffQueries $staff,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $until = $now->addMinutes(max(1, (int) config('scheduling.reminder_dispatch.before_minutes')));
        $limit = max(1, (int) config('scheduling.reminder_dispatch.batch_size'));
        $sessionIds = Session::query()
            ->whereNull('reminder_sent_at')
            ->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Confirmed])
            ->where('scheduled_start', '>', $now)
            ->where('scheduled_start', '<=', $until)
            ->orderBy('scheduled_start')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $dispatched = 0;
        foreach ($sessionIds as $sessionId) {
            if ($this->dispatchOne($sessionId, $now, $until)) {
                $dispatched++;
            }
        }

        $this->components->info(__('sessions::messages.reminders_dispatched', ['count' => $dispatched]));

        return self::SUCCESS;
    }

    private function dispatchOne(string $sessionId, CarbonImmutable $now, CarbonImmutable $until): bool
    {
        return DB::transaction(function () use ($sessionId, $now, $until): bool {
            /** @var Session|null $session */
            $session = Session::query()
                ->with(['participants' => static fn ($query) => $query->whereNull('revoked_at')])
                ->lockForUpdate()
                ->whereKey($sessionId)
                ->first();

            if ($session === null
                || $session->reminder_sent_at !== null
                || !in_array($session->status, [SessionStatus::Scheduled, SessionStatus::Confirmed], true)
                || $session->scheduled_start->lessThanOrEqualTo($now)
                || $session->scheduled_start->greaterThan($until)) {
                return false;
            }

            $studentUserIds = $this->studentUserIds($session);
            $teacherUserId = $this->staff->userIdForProfile(
                (string) $session->organization_id,
                (string) $session->staff_profile_id,
            );
            $hasRecipients = $studentUserIds !== [] || $teacherUserId !== null;

            if ($hasRecipients) {
                $this->dispatchEvent($session, $studentUserIds, $teacherUserId);
            }

            $session->forceFill(['reminder_sent_at' => $now])->save();

            return $hasRecipients;
        });
    }

    /** @return list<string> */
    private function studentUserIds(Session $session): array
    {
        $studentProfileIds = $session->participants
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $directory = $this->students->byIds((string) $session->organization_id, $studentProfileIds);

        return collect($studentProfileIds)
            ->map(static fn (string $id): ?string => $directory[$id]->userId ?? null)
            ->filter(static fn (?string $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $studentUserIds */
    private function dispatchEvent(Session $session, array $studentUserIds, ?string $teacherUserId): void
    {
        $this->events->dispatch(new SessionApproaching(
            sessionId: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            scheduledStart: $session->scheduled_start->toIso8601String(),
            scheduledEnd: $session->scheduled_end->toIso8601String(),
            studentUserIds: $studentUserIds,
            teacherUserId: $teacherUserId,
            courseName: is_array($session->title) ? $session->title : [],
            durationMinutes: (int) $session->scheduled_start->diffInMinutes($session->scheduled_end),
        ));
    }
}
