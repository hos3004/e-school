<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\ScheduleDefinitionValidator;
use Modules\Scheduling\Application\Services\ScheduleMaterializer;
use Modules\Scheduling\Domain\Events\ScheduleChanged;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class UpdateScheduleAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private ScheduleDefinitionValidator $validator,
        private ScheduleMaterializer $materializer,
        private SessionSchedulingGateway $sessions,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Schedule $schedule, array $data, string $actorId, string $reason): Schedule
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if (!$schedule->is_active) {
            throw BusinessRuleViolation::make('scheduling.schedule_inactive', 'scheduling::errors.schedule_inactive');
        }

        $data = $this->normalized($data);
        $this->validator->validate((string) $schedule->organization_id, $data);
        $weeklySlots = array_key_exists('weekly_slots', $data) ? (array) $data['weekly_slots'] : null;
        unset($data['weekly_slots']);
        $cutoff = CarbonImmutable::now('UTC')->addHours((int) config('scheduling.recurrence.edit_lock_hours'));
        $tracked = [
            'group_id', 'student_profile_id', 'course_id', 'staff_profile_id', 'session_type',
            'rrule', 'start_time', 'duration_minutes', 'timezone', 'starts_on', 'ends_on',
        ];
        $old = collect($schedule->getAttributes())->only($tracked)->all();
        $old['weekly_slots'] = $schedule->weeklySlots()->get(['weekday', 'start_time'])
            ->map(static fn ($slot): array => [
                'weekday' => $slot->weekday,
                'start_time' => (string) $slot->start_time,
            ])->all();

        $schedule = $this->transaction->run(function () use ($schedule, $data, $weeklySlots, $actorId, $reason, $cutoff, $old, $tracked): Schedule {
            $superseded = $this->sessions->supersedeFutureForSchedule(
                (string) $schedule->organization_id,
                (string) $schedule->getKey(),
                $cutoff,
                $actorId,
                $reason,
            );

            $schedule->fill($data);
            $schedule->save();
            if ($weeklySlots !== null) {
                $schedule->weeklySlots()->delete();
                $schedule->weeklySlots()->createMany(array_map(
                    static fn (array $slot): array => [
                        'organization_id' => (string) $schedule->organization_id,
                        'weekday' => $slot['weekday'],
                        'start_time' => $slot['start_time'],
                    ],
                    $weeklySlots,
                ));
            }
            $materialized = $this->materializer->materialize($schedule, $actorId, $cutoff);

            $this->audit->record(
                organizationId: (string) $schedule->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_updated',
                auditableType: 'schedules',
                auditableId: (string) $schedule->getKey(),
                oldValues: $old,
                newValues: [
                    ...collect($schedule->getAttributes())->only($tracked)->all(),
                    'weekly_slots' => $weeklySlots ?? $old['weekly_slots'],
                    'superseded_sessions' => $superseded,
                    'created_sessions' => $materialized->created,
                    'availability_warnings' => $materialized->outsideAvailabilityWarnings,
                ],
                reason: $reason,
            );

            return $schedule;
        });

        $this->events->dispatch(new ScheduleChanged(
            scheduleId: (string) $schedule->getKey(),
            effectiveFrom: $cutoff->toIso8601String(),
            actorId: $actorId,
        ));

        return $schedule;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        $targetType = (string) ($data['target_type'] ?? 'group');
        $hasWeeklySlots = array_key_exists('weekly_slots', $data);
        $weeklySlots = $hasWeeklySlots ? $this->normalizeWeeklySlots($data['weekly_slots']) : [];
        $weekdays = $weeklySlots === []
            ? (array) ($data['weekdays'] ?? [])
            : array_column($weeklySlots, 'weekday');

        $normalized = [
            'group_id' => $targetType === 'group' ? ($data['group_id'] ?? null) : null,
            'student_profile_id' => $targetType === 'student' ? ($data['student_profile_id'] ?? null) : null,
            'course_id' => $data['course_id'] ?? null,
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
            'session_type' => $targetType === 'group' ? 'group' : 'individual',
            'rrule' => WeeklyRecurrence::fromWeekdays(
                $weekdays,
                (int) ($data['interval_weeks'] ?? 1),
            )->toRRule(),
            'start_time' => $weeklySlots[0]['start_time'] ?? ($data['start_time'] ?? null),
            'duration_minutes' => (int) ($data['duration_minutes'] ?? config('scheduling.default_duration_minutes')),
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ];

        if ($hasWeeklySlots) {
            $normalized['weekly_slots'] = $weeklySlots;
        }

        return $normalized;
    }

    /** @return list<array{weekday: int, start_time: string}> */
    private function normalizeWeeklySlots(mixed $slots): array
    {
        if (!is_array($slots) || $slots === []) {
            throw BusinessRuleViolation::make('scheduling.weekly_slots_invalid', 'scheduling::errors.weekly_slots_invalid');
        }

        $normalized = [];
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot['weekday'], $slot['start_time'])) {
                throw BusinessRuleViolation::make('scheduling.weekly_slots_invalid', 'scheduling::errors.weekly_slots_invalid');
            }

            $normalized[] = [
                'weekday' => (int) $slot['weekday'],
                'start_time' => is_string($slot['start_time']) ? $slot['start_time'] : '',
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => $left['weekday'] <=> $right['weekday']);

        return $normalized;
    }
}
