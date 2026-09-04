<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\ScheduleDefinitionValidator;
use Modules\Scheduling\Application\Services\ScheduleMaterializer;
use Modules\Scheduling\Application\Services\ScheduleNotificationPayloadFactory;
use Modules\Scheduling\Domain\Events\ScheduleCreated;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class CreateScheduleAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private ScheduleDefinitionValidator $validator,
        private ScheduleMaterializer $materializer,
        private ScheduleNotificationPayloadFactory $notificationPayload,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(
        string $organizationId,
        array $data,
        string $actorId,
        string $reason,
    ): Schedule {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }

        $data = $this->normalized($data);
        $this->validator->validate($organizationId, $data);

        /** @var array{schedule: Schedule, warnings: int} $result */
        $result = $this->transaction->run(function () use ($organizationId, $data, $actorId, $reason): array {
            $schedule = new Schedule;
            $schedule->fill([
                ...$data,
                'organization_id' => $organizationId,
                'materialized_until' => $data['starts_on'],
                'is_active' => true,
                'created_by' => $actorId,
            ]);
            $schedule->save();

            $materialized = $this->materializer->materialize($schedule, $actorId);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_created',
                auditableType: 'schedules',
                auditableId: (string) $schedule->getKey(),
                oldValues: null,
                newValues: [
                    'group_id' => $schedule->group_id,
                    'student_profile_id' => $schedule->student_profile_id,
                    'course_id' => $schedule->course_id,
                    'staff_profile_id' => $schedule->staff_profile_id,
                    'rrule' => $schedule->rrule,
                    'created_sessions' => $materialized->created,
                    'availability_warnings' => $materialized->outsideAvailabilityWarnings,
                ],
                reason: $reason,
            );

            return ['schedule' => $schedule, 'warnings' => $materialized->outsideAvailabilityWarnings];
        });

        $this->events->dispatch(new ScheduleCreated(
            ...$this->notificationPayload->forSchedule($result['schedule']),
            actorId: $actorId,
        ));

        return $result['schedule'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        $targetType = (string) ($data['target_type'] ?? 'group');
        $rule = WeeklyRecurrence::fromWeekdays(
            (array) ($data['weekdays'] ?? []),
            (int) ($data['interval_weeks'] ?? 1),
        );

        return [
            'group_id' => $targetType === 'group' ? ($data['group_id'] ?? null) : null,
            'student_profile_id' => $targetType === 'student' ? ($data['student_profile_id'] ?? null) : null,
            'course_id' => $data['course_id'] ?? null,
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
            'session_type' => $targetType === 'group' ? 'group' : 'individual',
            'rrule' => $rule->toRRule(),
            'start_time' => $data['start_time'] ?? null,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? config('scheduling.default_duration_minutes')),
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ];
    }
}
