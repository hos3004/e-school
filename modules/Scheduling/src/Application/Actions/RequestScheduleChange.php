<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\ScheduleChangeDefinition;
use Modules\Scheduling\Application\Services\ScheduleDefinitionValidator;
use Modules\Scheduling\Application\Services\ScheduleNotificationPayloadFactory;
use Modules\Scheduling\Application\Services\ScheduleStudentAudience;
use Modules\Scheduling\Application\Services\WeeklyScheduleSummary;
use Modules\Scheduling\Domain\Enums\ScheduleChangeApprovalStatus;
use Modules\Scheduling\Domain\Enums\ScheduleChangeStatus;
use Modules\Scheduling\Domain\Events\ScheduleChangeRequested;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * المعلم يطلب تغيير الموعد الدائم لقالب جدوله.
 *
 * الطلب لا يغيّر شيئًا بذاته: يُنشئ صف قبول معلّقًا لكل طالب في الكورس، ويُخطر
 * الطلاب والمعلم والمشرف والإدارة. التطبيق يحدث في RespondToScheduleChange بعد
 * اكتمال قبول الجميع.
 */
final readonly class RequestScheduleChange
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private ScheduleChangeDefinition $definition,
        private ScheduleDefinitionValidator $validator,
        private ScheduleStudentAudience $audience,
        private ScheduleNotificationPayloadFactory $payloads,
        private WeeklyScheduleSummary $summary,
    ) {}

    public function execute(
        Schedule $schedule,
        string $staffProfileId,
        string $requestedBy,
        mixed $proposedSlots,
        ?int $intervalWeeks,
        string $reason,
    ): ScheduleChangeRequest {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if (!$schedule->is_active) {
            throw BusinessRuleViolation::make('scheduling.schedule_inactive', 'scheduling::errors.schedule_inactive');
        }
        if ((string) $schedule->staff_profile_id !== $staffProfileId) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_not_assigned_to_teacher',
                'scheduling::errors.schedule_not_assigned_to_teacher',
            );
        }

        $organizationId = (string) $schedule->organization_id;
        $individual = $schedule->student_profile_id !== null;
        $slots = $this->definition->normalizeSlots($proposedSlots, $individual);
        $currentInterval = $this->definition->intervalWeeks($schedule);
        $interval = $intervalWeeks ?? $currentInterval;
        $current = $this->definition->currentSlots($schedule);

        if ($slots === $current && $interval === $currentInterval) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_not_different',
                'scheduling::errors.schedule_change_not_different',
            );
        }

        $this->validator->validate($organizationId, $this->definition->toScheduleData($schedule, $slots, $interval));

        if (ScheduleChangeRequest::query()->forOrganization($organizationId)
            ->where('schedule_id', (string) $schedule->getKey())
            ->pending()
            ->notExpired()
            ->exists()) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_already_pending',
                'scheduling::errors.schedule_change_already_pending',
            );
        }

        $students = $this->audience->forSchedule($schedule);
        if ($students === []) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_no_students',
                'scheduling::errors.schedule_change_no_students',
            );
        }

        $expiresAt = CarbonImmutable::now('UTC')->addHours(
            max(1, (int) config('scheduling.permanent_change.student_response_sla_hours')),
        );

        $request = $this->transaction->run(function () use (
            $organizationId,
            $schedule,
            $staffProfileId,
            $requestedBy,
            $slots,
            $interval,
            $individual,
            $reason,
            $expiresAt,
            $students,
            $current,
        ): ScheduleChangeRequest {
            $request = ScheduleChangeRequest::query()->create([
                'organization_id' => $organizationId,
                'schedule_id' => (string) $schedule->getKey(),
                'staff_profile_id' => $staffProfileId,
                'requested_by' => $requestedBy,
                'status' => ScheduleChangeStatus::Pending,
                'proposed_weekdays' => array_column($slots, 'weekday'),
                'proposed_weekly_slots' => $individual ? $slots : null,
                'proposed_start_time' => $slots[0]['start_time'],
                'proposed_interval_weeks' => $interval,
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);

            foreach ($students as $student) {
                $request->approvals()->create([
                    'organization_id' => $organizationId,
                    'student_profile_id' => $student['student_profile_id'],
                    'student_user_id' => $student['user_id'],
                    'status' => ScheduleChangeApprovalStatus::Pending,
                ]);
            }

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $requestedBy,
                actorType: 'user',
                action: 'scheduling.schedule_change_requested',
                auditableType: 'schedule_change_requests',
                auditableId: (string) $request->getKey(),
                oldValues: ['weekly_slots' => $current],
                newValues: [
                    'schedule_id' => (string) $schedule->getKey(),
                    'weekly_slots' => $slots,
                    'interval_weeks' => $interval,
                    'students_count' => count($students),
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                reason: $reason,
            );

            return $request;
        });

        $notification = $this->payloads->forSchedule($schedule);
        $this->events->dispatch(new ScheduleChangeRequested(
            requestId: (string) $request->getKey(),
            scheduleId: (string) $schedule->getKey(),
            organizationId: $organizationId,
            staffProfileId: $staffProfileId,
            courseId: (string) $schedule->course_id,
            studentUserIds: $notification['studentUserIds'],
            teacherUserId: $notification['teacherUserId'],
            courseName: $notification['courseName'],
            targetName: $notification['targetName'],
            teacherName: $notification['teacherName'],
            currentSchedule: $this->summary->localized($current),
            proposedSchedule: $this->summary->localized($slots),
            timezone: (string) $schedule->timezone,
            expiresAt: $expiresAt->toIso8601String(),
            studentsCount: count($students),
            actorId: $requestedBy,
        ));

        return $request;
    }
}
