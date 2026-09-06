<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\IndividualTeachingAssignment;

final readonly class IndividualTeachingAssignmentQueries implements IndividualTeachingAssignments
{
    public function activeForTeacher(string $organizationId, string $staffProfileId): array
    {
        $today = now('UTC')->toDateString();

        return Schedule::query()->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)->active()->whereNotNull('student_profile_id')
            ->where('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->get(['id', 'student_profile_id', 'course_id', 'session_type'])
            ->map(static fn (Schedule $schedule): IndividualTeachingAssignment => new IndividualTeachingAssignment(
                (string) $schedule->id, (string) $schedule->student_profile_id,
                (string) $schedule->course_id, (string) $schedule->session_type,
            ))->all();
    }
}
