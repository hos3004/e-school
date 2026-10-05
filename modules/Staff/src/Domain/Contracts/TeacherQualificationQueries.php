<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

interface TeacherQualificationQueries
{
    /** @return list<string> */
    public function courseIdsForTeacher(string $staffProfileId): array;

    /**
     * @return list<string> معرّفات ملفات المعلمين المؤهلين لتدريس الكورس
     */
    public function qualifiedTeacherIdsForCourse(string $courseId): array;

    public function isQualified(string $staffProfileId, string $courseId): bool;

    public function genderOf(string $staffProfileId): ?string;
}
