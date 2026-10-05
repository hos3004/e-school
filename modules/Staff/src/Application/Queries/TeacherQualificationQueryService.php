<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Models\TeacherCourse;

final readonly class TeacherQualificationQueryService implements TeacherQualificationQueries
{
    public function courseIdsForTeacher(string $staffProfileId): array
    {
        return TeacherCourse::query()
            ->where('staff_profile_id', $staffProfileId)
            ->whereNull('revoked_at')
            ->orderBy('course_id')
            ->pluck('course_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string> معرّفات ملفات المعلمين المؤهلين لتدريس الكورس
     */
    public function qualifiedTeacherIdsForCourse(string $courseId): array
    {
        /** @var list<string> $ids */
        $ids = TeacherCourse::query()
            ->where('course_id', $courseId)
            ->whereNull('revoked_at')
            ->orderBy('staff_profile_id')
            ->pluck('staff_profile_id')
            ->all();

        return $ids;
    }

    public function isQualified(string $staffProfileId, string $courseId): bool
    {
        return TeacherCourse::query()
            ->where('staff_profile_id', $staffProfileId)
            ->where('course_id', $courseId)
            ->whereNull('revoked_at')
            ->exists();
    }

    public function genderOf(string $staffProfileId): ?string
    {
        /** @var string|null $gender */
        $gender = DB::table('staff_profiles')
            ->where('id', $staffProfileId)
            ->value('gender');

        return $gender;
    }
}
