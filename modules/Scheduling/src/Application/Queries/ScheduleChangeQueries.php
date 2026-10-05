<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Scheduling\Application\Services\ScheduleChangeDefinition;
use Modules\Scheduling\Application\Services\WeeklyScheduleSummary;
use Modules\Scheduling\Domain\Enums\ScheduleChangeApprovalStatus;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeApproval;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;

/**
 * قراءات عرض طلبات تغيير الموعد الدائم لبوابتَي المعلم والطالب.
 *
 * ترجع مصفوفات عرض جاهزة؛ لا تُسرَّب نماذج Eloquent خارج الموديول.
 */
final readonly class ScheduleChangeQueries
{
    public function __construct(
        private SchedulingAdministrationQueryService $labels,
        private ScheduleChangeDefinition $definition,
        private WeeklyScheduleSummary $summary,
    ) {}

    /**
     * قوالب جدول المعلم النشطة مع حالة طلب التغيير المعلّق إن وُجد.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherSchedules(string $organizationId, string $staffProfileId, string $locale): array
    {
        $schedules = Schedule::query()
            ->forOrganization($organizationId)
            ->forStaff($staffProfileId)
            ->active()
            ->with('weeklySlots')
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $pending = ScheduleChangeRequest::query()
            ->forOrganization($organizationId)
            ->whereIn('schedule_id', $schedules->modelKeys())
            ->pending()
            ->notExpired()
            ->with('approvals')
            ->get()
            ->keyBy(static fn (ScheduleChangeRequest $request): string => (string) $request->schedule_id);

        return $schedules->map(function (Schedule $schedule) use ($organizationId, $locale, $pending): array {
            $slots = $this->definition->currentSlots($schedule);
            $request = $pending->get((string) $schedule->getKey());

            return [
                'id' => (string) $schedule->getKey(),
                'courseLabel' => $this->labels->courseLabel($organizationId, (string) $schedule->course_id),
                'targetLabel' => $schedule->student_profile_id === null
                    ? $this->labels->groupLabel($organizationId, $schedule->group_id === null ? null : (string) $schedule->group_id)
                    : $this->labels->studentLabel($organizationId, (string) $schedule->student_profile_id),
                'individual' => $schedule->student_profile_id !== null,
                'timezone' => (string) $schedule->timezone,
                'durationMinutes' => (int) $schedule->duration_minutes,
                'intervalWeeks' => $this->definition->intervalWeeks($schedule),
                'slots' => $slots,
                'currentSummary' => $this->summary->forLocale($slots, $locale),
                'pendingRequest' => $request instanceof ScheduleChangeRequest
                    ? $this->pendingSummary($organizationId, $request, $locale)
                    : null,
            ];
        })->all();
    }

    /**
     * طلبات تغيير الموعد المعلّقة التي تنتظر رد هذا الطالب.
     *
     * @return list<array<string, mixed>>
     */
    public function studentPendingRequests(string $organizationId, string $studentProfileId, string $locale): array
    {
        $approvals = ScheduleChangeApproval::query()
            ->forOrganization($organizationId)
            ->where('student_profile_id', $studentProfileId)
            ->where('status', ScheduleChangeApprovalStatus::Pending->value)
            ->with(['request.schedule.weeklySlots'])
            ->get();

        $items = [];

        foreach ($approvals as $approval) {
            $request = $approval->request;
            if (!$request instanceof ScheduleChangeRequest
                || !$request->status->isPending()
                || $request->expires_at->isPast()) {
                continue;
            }

            $schedule = $request->schedule;
            if (!$schedule instanceof Schedule) {
                continue;
            }

            $items[] = [
                'id' => (string) $request->getKey(),
                'courseLabel' => $this->labels->courseLabel($organizationId, (string) $schedule->course_id),
                'teacherLabel' => $this->labels->teacherLabel($organizationId, (string) $request->staff_profile_id),
                'timezone' => (string) $schedule->timezone,
                'currentSummary' => $this->summary->forLocale($this->definition->currentSlots($schedule), $locale),
                'proposedSummary' => $this->summary->forLocale($this->definition->proposedSlots($request), $locale),
                'reason' => (string) $request->reason,
                'expiresAt' => $request->expires_at->toIso8601String(),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function pendingSummary(string $organizationId, ScheduleChangeRequest $request, string $locale): array
    {
        $approvals = $request->approvals->map(fn (ScheduleChangeApproval $approval): array => [
            'studentLabel' => $this->labels->studentLabel($organizationId, (string) $approval->student_profile_id),
            'status' => $approval->status->value,
            'statusLabel' => $approval->status->label(),
        ])->all();

        return [
            'id' => (string) $request->getKey(),
            'status' => $request->status->value,
            'statusLabel' => $request->status->label(),
            'proposedSummary' => $this->summary->forLocale($this->definition->proposedSlots($request), $locale),
            'reason' => (string) $request->reason,
            'expiresAt' => $request->expires_at->toIso8601String(),
            'acceptedCount' => count(array_filter(
                $approvals,
                static fn (array $approval): bool => $approval['status'] === ScheduleChangeApprovalStatus::Accepted->value,
            )),
            'totalCount' => count($approvals),
            'approvals' => $approvals,
        ];
    }
}
