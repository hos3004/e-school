<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** Scheduling owns its models; the Quran workspace receives primitive arrays only. */
final readonly class ConsoleQuranScheduleService
{
    public function __construct(
        private UpdateScheduleAction $update,
        private Transaction $transaction,
        private StudentDirectoryQueries $students,
        private UserQueryService $users,
    ) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(string $organizationId, string $studentId, string $scheduleId, string $courseId, array $data, string $actorId): array
    {
        return $this->transaction->run(function () use ($organizationId, $studentId, $scheduleId, $courseId, $data, $actorId): array {
            $schedule = $this->owned($organizationId, $studentId, $scheduleId, $courseId, true);
            Gate::authorize('update', $schedule);
            $student = $this->students->find($organizationId, $studentId);
            $account = $student === null ? null : $this->users->findSummary($student->userId);
            if ($account === null || $account->organizationId !== $organizationId || !$account->isActive()) {
                throw BusinessRuleViolation::make('scheduling.student_not_schedulable', 'scheduling::errors.student_not_schedulable');
            }
            $saved = $this->update->execute($schedule, [
                ...$data, 'target_type' => 'student', 'student_profile_id' => $studentId, 'group_id' => null, 'course_id' => $courseId,
            ], $actorId, __('console_quran.audit_update'));

            return $this->summary($saved);
        });
    }

    /** @return array<string, mixed> */
    public function authorizeAvailability(string $organizationId, string $studentId, string $scheduleId, string $courseId): array
    {
        $schedule = $this->owned($organizationId, $studentId, $scheduleId, $courseId);
        Gate::authorize('update', $schedule);

        return $this->summary($schedule);
    }

    private function owned(string $organizationId, string $studentId, string $scheduleId, string $courseId, bool $lock = false): Schedule
    {
        return Schedule::query()->forOrganization($organizationId)->where('student_profile_id', $studentId)
            ->where('course_id', $courseId)->where('session_type', 'individual')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($scheduleId);
    }

    /** @return array<string, mixed> */
    private function summary(Schedule $schedule): array
    {
        $rule = WeeklyRecurrence::fromRRule($schedule->rrule);
        $slots = $schedule->weeklySlots()->get()->map(static fn ($slot): array => [
            'weekday' => (int) $slot->weekday, 'start_time' => substr((string) $slot->start_time, 0, 5),
        ])->values()->all();
        if ($slots === []) {
            $slots = array_map(static fn (int $day): array => ['weekday' => $day, 'start_time' => substr($schedule->start_time, 0, 5)], $rule->weekdays);
        }

        return [
            'id' => $schedule->id, 'staff_profile_id' => $schedule->staff_profile_id,
            'weekly_slots' => $slots, 'duration_minutes' => $schedule->duration_minutes,
            'interval_weeks' => $rule->intervalWeeks, 'timezone' => $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(), 'ends_on' => $schedule->ends_on?->toDateString(),
        ];
    }
}
