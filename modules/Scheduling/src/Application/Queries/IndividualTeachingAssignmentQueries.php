<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\IndividualTeachingAssignment;

final readonly class IndividualTeachingAssignmentQueries implements IndividualTeachingAssignments
{
    public function activeForTeacher(string $organizationId, string $staffProfileId): array
    {
        $today = now('UTC')->toDateString();

        $scheduled = Schedule::query()->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)->active()->whereNotNull('student_profile_id')
            ->where('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->get(['id', 'student_profile_id', 'course_id', 'session_type'])
            ->map(static fn (Schedule $schedule): IndividualTeachingAssignment => new IndividualTeachingAssignment(
                (string) $schedule->id, (string) $schedule->student_profile_id,
                (string) $schedule->course_id, (string) $schedule->session_type,
            ))->all();
        $pending = PendingTeachingAssignment::query()
            ->where('organization_id', $organizationId)->where('staff_profile_id', $staffProfileId)
            ->whereNotExists(function ($query) use ($organizationId, $staffProfileId, $today): void {
                $query->selectRaw('1')->from('schedules')
                    ->whereColumn('schedules.student_profile_id', 'pending_teaching_assignments.student_profile_id')
                    ->whereColumn('schedules.course_id', 'pending_teaching_assignments.course_id')
                    ->where('schedules.organization_id', $organizationId)
                    ->where('schedules.staff_profile_id', $staffProfileId)->where('schedules.is_active', true)
                    ->where(fn ($dates) => $dates->whereNull('schedules.ends_on')->orWhere('schedules.ends_on', '>=', $today));
            })
            ->get()->map(static fn (PendingTeachingAssignment $assignment): IndividualTeachingAssignment => new IndividualTeachingAssignment($assignment->id, $assignment->student_profile_id,
                $assignment->course_id, $assignment->session_type, true))->all();

        return [...$scheduled, ...$pending];
    }
}
