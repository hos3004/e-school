<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** Scheduling-owned model access for the console; only primitive projections leave this service. */
final readonly class ConsoleGroupScheduleService
{
    public function __construct(private CreateScheduleAction $create, private UpdateScheduleAction $update, private Transaction $transaction) {}

    /** @return list<array<string, mixed>> */
    public function listing(string $organizationId, ?string $groupId = null, ?string $courseId = null): array
    {
        Gate::authorize('viewAny', Schedule::class);

        return Schedule::query()->forOrganization($organizationId)->where('session_type', 'group')->where('is_active', true)
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->when($courseId !== null, fn ($query) => $query->where('course_id', $courseId))
            ->orderBy('starts_on')->get()->map(fn (Schedule $schedule): array => $this->summary($schedule))->all();
    }

    /** @return array<string, mixed> */
    public function editable(string $organizationId, string $scheduleId): array
    {
        $schedule = $this->owned($organizationId, $scheduleId);
        Gate::authorize('update', $schedule);

        return $this->summary($schedule);
    }

    /** @param array<string, mixed> $data */
    public function save(string $organizationId, ?string $scheduleId, array $data, string $actorId): string
    {
        return $this->transaction->run(function () use ($organizationId, $scheduleId, $data, $actorId): string {
            $data['target_type'] = 'group';
            $data['student_profile_id'] = null;
            if ($scheduleId === null) {
                Gate::authorize('create', Schedule::class);

                return (string) $this->create->execute($organizationId, $data, $actorId, __('console_sessions.audit_create'))->id;
            }
            $schedule = $this->owned($organizationId, $scheduleId, true);
            Gate::authorize('update', $schedule);
            if ($schedule->group_id !== $data['group_id'] || $schedule->course_id !== $data['course_id']) {
                throw BusinessRuleViolation::make('scheduling.target_invalid', 'scheduling::errors.target_invalid');
            }

            return (string) $this->update->execute($schedule, $data, $actorId, __('console_sessions.audit_update'))->id;
        });
    }

    private function owned(string $organizationId, string $scheduleId, bool $lock = false): Schedule
    {
        return Schedule::query()->forOrganization($organizationId)->where('session_type', 'group')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($scheduleId);
    }

    /** @return array<string, mixed> */
    private function summary(Schedule $schedule): array
    {
        $rule = WeeklyRecurrence::fromRRule($schedule->rrule);

        return [
            'id' => (string) $schedule->id, 'group_id' => (string) $schedule->group_id, 'course_id' => (string) $schedule->course_id,
            'staff_profile_id' => (string) $schedule->staff_profile_id, 'weekdays' => $rule->weekdays,
            'start_time' => substr($schedule->start_time, 0, 5), 'duration_minutes' => $schedule->duration_minutes,
            'interval_weeks' => $rule->intervalWeeks, 'timezone' => $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(), 'ends_on' => $schedule->ends_on?->toDateString(),
            'is_active' => $schedule->is_active, 'materialized_until' => $schedule->materialized_until->toDateString(),
        ];
    }
}
