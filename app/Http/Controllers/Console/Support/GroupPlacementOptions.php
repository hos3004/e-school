<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;

/**
 * مجموعات كورس واحد المفتوحة للتسكين، بصيغة خيارات للوحة.
 *
 * تُستعمل من صفحة الطالب ومن نموذج التسجيل معًا، فتبقى قائمة واحدة بشروط
 * واحدة: المجموعة تابعة للمؤسسة، والبرنامج مربوط بها، وفيها مقعد. المجموعة
 * قيد التخطيط تظهر موسومة مسودة لأن التسكين فيها انتساب معلّق حتى التفعيل.
 */
final readonly class GroupPlacementOptions
{
    public function __construct(
        private AcademicCatalogQueries $catalog,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
    ) {}

    /** @return list<array{value: string, label: string, seats: int|null, draft: bool, teachers: string}> */
    public function forCourse(string $organizationId, string $courseId): array
    {
        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        if ($course === null || $course->programId === null) {
            return [];
        }

        $open = $this->groups->openForPlacement($organizationId, $course->programId, $courseId);
        $teacherIds = [];

        foreach ($open as $group) {
            foreach ($group->teacherProfileIds as $teacherId) {
                $teacherIds[] = $teacherId;
            }
        }

        $names = $this->staff->namesForProfiles($organizationId, array_values(array_unique($teacherIds)));

        return array_map(fn ($group): array => [
            'value' => $group->id,
            'label' => self::label($group->name, $group->code),
            'seats' => $group->capacity === null ? null : $group->remainingSeats,
            'draft' => $group->isDraft(),
            'teachers' => implode('، ', array_values(array_filter(array_map(
                static fn (string $id): ?string => $names[$id] ?? null,
                $group->teacherProfileIds,
            )))),
        ], $open);
    }

    /** @param array<string, mixed> $names */
    public static function label(array $names, string $fallback): string
    {
        $value = $names[app()->getLocale()] ?? $names['ar'] ?? $names['en'] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
