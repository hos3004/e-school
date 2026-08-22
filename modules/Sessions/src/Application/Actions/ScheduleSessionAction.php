<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionScheduled;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء حصة جديدة وجدولتها وإعلانها للطرفين.
 */
final readonly class ScheduleSessionAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?string $actorId = null): Session
    {
        $start = CarbonImmutable::parse($data['scheduled_start'], 'UTC');
        $end = CarbonImmutable::parse($data['scheduled_end'], 'UTC');

        if ($start->lessThan(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make(
                'sessions.start_in_past',
                'sessions::errors.start_in_past',
            );
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw BusinessRuleViolation::make(
                'sessions.end_before_start',
                'sessions::errors.end_before_start',
            );
        }

        /** @var list<array{id: string, scheduled_start: string, scheduled_end: string}> $overlaps */
        $overlaps = Session::query()
            ->select(['id', 'scheduled_start', 'scheduled_end'])
            ->where('staff_profile_id', $data['staff_profile_id'])
            ->whereIn('status', [
                SessionStatus::Draft,
                SessionStatus::Scheduled,
                SessionStatus::Confirmed,
                SessionStatus::InProgress,
                SessionStatus::AwaitingReview,
            ])
            ->whereNull('deleted_at')
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start)
            ->get()
            ->all();

        if ($overlaps !== []) {
            throw BusinessRuleViolation::make(
                'sessions.teacher_double_booking',
                'sessions::errors.teacher_double_booking',
                ['conflicting_session_id' => $overlaps[0]['id']],
            );
        }

        [$session, $event] = DB::transaction(function () use ($data, $start, $end): array {
            $session = new Session;
            $session->fill([
                ...$data,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'status' => SessionStatus::Scheduled,
            ]);
            $session->save();

            SessionStatusHistory::query()->create([
                'session_id' => $session->id,
                'from_status' => null,
                'to_status' => SessionStatus::Scheduled->value,
                'reason' => null,
                'changed_by' => (string) auth()->id(),
                'changed_at' => CarbonImmutable::now('UTC'),
            ]);

            return [$session, new SessionScheduled(
                sessionId: $session->id,
                organizationId: $session->organization_id,
                courseId: $session->course_id,
                staffProfileId: $session->staff_profile_id,
                scheduledStart: $start->toIso8601String(),
                scheduledEnd: $end->toIso8601String(),
                groupId: $session->group_id,
            )];
        });

        $this->events->dispatch($event);

        return $session;
    }
}
