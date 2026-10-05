<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\ScheduleChangeDefinition;
use Modules\Scheduling\Application\Services\ScheduleNotificationPayloadFactory;
use Modules\Scheduling\Application\Services\WeeklyScheduleSummary;
use Modules\Scheduling\Domain\Enums\ScheduleChangeApprovalStatus;
use Modules\Scheduling\Domain\Enums\ScheduleChangeStatus;
use Modules\Scheduling\Domain\Events\ScheduleChangeApplied;
use Modules\Scheduling\Domain\Events\ScheduleChangeRejected;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeApproval;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رد الطالب على طلب تغيير الموعد الدائم.
 *
 * شرط العميل: لا يُطبَّق الموعد الجديد إلا بقبول كل طلاب الكورس. رفض طالب واحد
 * ينهي الطلب فورًا، وانقضاء المهلة ينهيه كذلك؛ في الحالتين يُخطر المعلم والمشرف
 * والإدارة. التطبيق نفسه يمر عبر UpdateScheduleAction ليحتفظ بالحصص داخل نافذة
 * القفل ويعيد توليد ما بعدها ويسجّل التدقيق.
 */
final readonly class RespondToScheduleChange
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private UpdateScheduleAction $updateSchedule,
        private ScheduleChangeDefinition $definition,
        private ScheduleNotificationPayloadFactory $payloads,
        private WeeklyScheduleSummary $summary,
    ) {}

    public function execute(
        ScheduleChangeRequest $request,
        string $studentProfileId,
        string $respondedBy,
        bool $accepted,
        ?string $note = null,
    ): ScheduleChangeRequest {
        if (!$request->status->isPending()) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_not_pending',
                'scheduling::errors.schedule_change_not_pending',
            );
        }

        $schedule = $request->schedule()->first();
        if (!$schedule instanceof Schedule) {
            throw BusinessRuleViolation::make('scheduling.schedule_not_found', 'scheduling::errors.schedule_not_found');
        }

        if ($request->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            $this->close($request, $schedule, $respondedBy, ScheduleChangeStatus::Expired, 'expired');

            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_expired',
                'scheduling::errors.schedule_change_expired',
            );
        }

        $approval = $request->approvals()
            ->where('student_profile_id', $studentProfileId)
            ->first();
        if (!$approval instanceof ScheduleChangeApproval
            || $approval->status !== ScheduleChangeApprovalStatus::Pending) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_response_not_allowed',
                'scheduling::errors.schedule_change_response_not_allowed',
            );
        }

        $note = $note === null ? null : (trim($note) === '' ? null : trim($note));
        $slots = $this->definition->proposedSlots($request);
        $previous = $this->definition->currentSlots($schedule);
        $applied = false;

        $request = $this->transaction->run(function () use (
            $request,
            $schedule,
            $approval,
            $respondedBy,
            $accepted,
            $note,
            $slots,
            &$applied,
        ): ScheduleChangeRequest {
            // ردّان متزامنان على نفس الطلب لا يجوز أن يطبّقا الجدول مرتين.
            $locked = ScheduleChangeRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();
            if (!$locked instanceof ScheduleChangeRequest || !$locked->status->isPending()) {
                throw BusinessRuleViolation::make(
                    'scheduling.schedule_change_not_pending',
                    'scheduling::errors.schedule_change_not_pending',
                );
            }

            $approval->forceFill([
                'status' => $accepted
                    ? ScheduleChangeApprovalStatus::Accepted
                    : ScheduleChangeApprovalStatus::Rejected,
                'responded_by' => $respondedBy,
                'responded_at' => CarbonImmutable::now('UTC'),
                'note' => $note,
            ])->save();

            $this->audit->record(
                organizationId: (string) $request->organization_id,
                actorId: $respondedBy,
                actorType: 'user',
                action: 'scheduling.schedule_change_response_recorded',
                auditableType: 'schedule_change_approvals',
                auditableId: (string) $approval->getKey(),
                oldValues: ['status' => ScheduleChangeApprovalStatus::Pending->value],
                newValues: [
                    'schedule_change_request_id' => (string) $request->getKey(),
                    'student_profile_id' => (string) $approval->student_profile_id,
                    'status' => $approval->status->value,
                ],
                reason: $note ?? (string) $request->reason,
            );

            if (!$accepted) {
                $request->forceFill([
                    'status' => ScheduleChangeStatus::Rejected,
                    'decided_by' => $respondedBy,
                    'decided_at' => CarbonImmutable::now('UTC'),
                ])->save();

                return $request;
            }

            $stillPending = $request->approvals()
                ->where('status', ScheduleChangeApprovalStatus::Pending->value)
                ->exists();
            if ($stillPending) {
                return $request;
            }

            $this->updateSchedule->execute(
                $schedule,
                $this->definition->toScheduleData($schedule, $slots, (int) $request->proposed_interval_weeks),
                (string) $request->requested_by,
                (string) $request->reason,
            );

            $request->forceFill([
                'status' => ScheduleChangeStatus::Applied,
                'decided_by' => $respondedBy,
                'decided_at' => CarbonImmutable::now('UTC'),
                'applied_at' => CarbonImmutable::now('UTC'),
            ])->save();
            $applied = true;

            return $request;
        });

        if ($request->status === ScheduleChangeStatus::Rejected) {
            $this->dispatchClosed($request, $schedule, 'rejected_by_student');
        } elseif ($applied) {
            $this->dispatchApplied($request, $schedule, $previous, $slots);
        }

        return $request;
    }

    /** ينهي طلبًا لم يكتمل قبوله ويخطر الأطراف — يُستخدم للانقضاء والسحب. */
    public function close(
        ScheduleChangeRequest $request,
        Schedule $schedule,
        string $actorId,
        ScheduleChangeStatus $status,
        string $outcome,
    ): ScheduleChangeRequest {
        if (!$request->status->canTransitionTo($status)) {
            throw BusinessRuleViolation::make(
                'scheduling.schedule_change_invalid_transition',
                'scheduling::errors.schedule_change_invalid_transition',
                ['from' => $request->status->value, 'to' => $status->value],
            );
        }

        $request = $this->transaction->run(function () use ($request, $actorId, $status, $outcome): ScheduleChangeRequest {
            $old = $request->status->value;
            $request->forceFill([
                'status' => $status,
                'decided_by' => $actorId,
                'decided_at' => CarbonImmutable::now('UTC'),
            ])->save();

            $this->audit->record(
                organizationId: (string) $request->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.schedule_change_closed',
                auditableType: 'schedule_change_requests',
                auditableId: (string) $request->getKey(),
                oldValues: ['status' => $old],
                newValues: ['status' => $status->value, 'outcome' => $outcome],
                reason: (string) $request->reason,
            );

            return $request;
        });

        $this->dispatchClosed($request, $schedule, $outcome);

        return $request;
    }

    private function dispatchClosed(ScheduleChangeRequest $request, Schedule $schedule, string $outcome): void
    {
        $notification = $this->payloads->forSchedule($schedule);

        $this->events->dispatch(new ScheduleChangeRejected(
            requestId: (string) $request->getKey(),
            scheduleId: (string) $schedule->getKey(),
            organizationId: (string) $request->organization_id,
            staffProfileId: (string) $request->staff_profile_id,
            courseId: (string) $schedule->course_id,
            studentUserIds: $notification['studentUserIds'],
            teacherUserId: $notification['teacherUserId'],
            courseName: $notification['courseName'],
            targetName: $notification['targetName'],
            teacherName: $notification['teacherName'],
            proposedSchedule: $this->summary->localized($this->definition->proposedSlots($request)),
            outcome: $outcome,
            actorId: $request->decided_by === null ? null : (string) $request->decided_by,
        ));
    }

    /**
     * @param list<array{weekday: int, start_time: string}> $previous
     * @param list<array{weekday: int, start_time: string}> $slots
     */
    private function dispatchApplied(
        ScheduleChangeRequest $request,
        Schedule $schedule,
        array $previous,
        array $slots,
    ): void {
        $schedule->refresh();
        $notification = $this->payloads->forSchedule($schedule);
        $effectiveFrom = CarbonImmutable::now('UTC')
            ->addHours((int) config('scheduling.recurrence.edit_lock_hours'));

        $this->events->dispatch(new ScheduleChangeApplied(
            requestId: (string) $request->getKey(),
            scheduleId: (string) $schedule->getKey(),
            organizationId: (string) $request->organization_id,
            staffProfileId: (string) $request->staff_profile_id,
            courseId: (string) $schedule->course_id,
            studentUserIds: $notification['studentUserIds'],
            teacherUserId: $notification['teacherUserId'],
            courseName: $notification['courseName'],
            targetName: $notification['targetName'],
            teacherName: $notification['teacherName'],
            currentSchedule: $this->summary->localized($previous),
            proposedSchedule: $this->summary->localized($slots),
            timezone: (string) $schedule->timezone,
            effectiveFrom: $effectiveFrom->toIso8601String(),
            actorId: $request->decided_by === null ? null : (string) $request->decided_by,
        ));
    }
}
