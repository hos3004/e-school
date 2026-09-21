<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;

final readonly class AcademicCatalogQueryService implements AcademicCatalogQueries
{
    public function programs(string $organizationId): array
    {
        return Program::query()
            ->forOrganization($organizationId)
            ->open()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(static fn (Program $program): AcademicCatalogItemData => self::programData($program))
            ->values()
            ->all();
    }

    public function courses(string $organizationId, string $programId): array
    {
        return Course::query()
            ->forOrganization($organizationId)
            ->open()
            ->active()
            ->whereHas('level', static fn ($query) => $query->where('program_id', $programId))
            ->with('level')
            ->orderBy('code')
            ->get()
            ->map(static fn (Course $course): AcademicCatalogItemData => self::courseData($course))
            ->values()
            ->all();
    }

    public function levels(string $organizationId, string $programId): array
    {
        return Level::query()
            ->where('program_id', $programId)
            ->whereHas('program', static fn ($query) => $query->where('organization_id', $organizationId))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(static fn (Level $level): AcademicCatalogItemData => self::levelData($level))
            ->values()
            ->all();
    }

    public function programsByIds(string $organizationId, array $programIds): array
    {
        $ids = self::normalizedIds($programIds);

        if ($ids === []) {
            return [];
        }

        return Program::query()
            ->forOrganization($organizationId)
            ->whereKey($ids)
            ->get()
            ->map(static fn (Program $program): AcademicCatalogItemData => self::programData($program))
            ->keyBy(static fn (AcademicCatalogItemData $item): string => $item->id)
            ->all();
    }

    public function coursesByIds(string $organizationId, array $courseIds): array
    {
        $ids = self::normalizedIds($courseIds);

        if ($ids === []) {
            return [];
        }

        return Course::query()
            ->forOrganization($organizationId)
            ->whereKey($ids)
            ->with('level')
            ->get()
            ->map(static fn (Course $course): AcademicCatalogItemData => self::courseData($course))
            ->keyBy(static fn (AcademicCatalogItemData $item): string => $item->id)
            ->all();
    }

    public function levelsByIds(string $organizationId, array $levelIds): array
    {
        $ids = self::normalizedIds($levelIds);

        if ($ids === []) {
            return [];
        }

        return Level::query()
            ->whereKey($ids)
            ->whereHas('program', static fn ($query) => $query->where('organization_id', $organizationId))
            ->get()
            ->map(static fn (Level $level): AcademicCatalogItemData => self::levelData($level))
            ->keyBy(static fn (AcademicCatalogItemData $item): string => $item->id)
            ->all();
    }

    private static function programData(Program $program): AcademicCatalogItemData
    {
        return new AcademicCatalogItemData(
            id: (string) $program->getKey(),
            code: (string) $program->code,
            name: is_array($program->name) ? $program->name : [],
        );
    }

    private static function courseData(Course $course): AcademicCatalogItemData
    {
        return new AcademicCatalogItemData(
            id: (string) $course->getKey(),
            code: (string) $course->code,
            name: is_array($course->name) ? $course->name : [],
            programId: $course->level === null ? null : (string) $course->level->program_id,
            sessionMode: $course->session_mode?->value,
            levelId: (string) $course->level_id,
            defaultDurationMinutes: $course->default_duration_minutes === null
                ? null
                : (int) $course->default_duration_minutes,
            sessionsPerWeek: $course->sessions_per_week === null ? null : (int) $course->sessions_per_week,
            totalSessions: $course->total_sessions === null ? null : (int) $course->total_sessions,
        );
    }

    private static function levelData(Level $level): AcademicCatalogItemData
    {
        return new AcademicCatalogItemData(
            id: (string) $level->getKey(),
            code: (string) $level->code,
            name: is_array($level->name) ? $level->name : [],
            programId: (string) $level->program_id,
        );
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private static function normalizedIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
    }

    public function closureFactsForProgram(string $organizationId, string $programId): array
    {
        $levelIds = Level::query()->where('program_id', $programId)->pluck('id')->all();

        $courses = Course::query()
            ->forOrganization($organizationId)
            ->whereIn('level_id', $levelIds);

        return [
            'levels_total' => count($levelIds),
            'courses_total' => (clone $courses)->count(),
            'courses_open' => (clone $courses)->whereNull('closed_at')->count(),
            'courses_active' => (clone $courses)->where('is_active', true)->whereNull('closed_at')->count(),
            'courses_closed' => (clone $courses)->whereNotNull('closed_at')->count(),
        ];
    }

    public function courseIdsForProgram(string $organizationId, string $programId): array
    {
        $levelIds = Level::query()->where('program_id', $programId)->pluck('id')->all();

        return Course::query()
            ->forOrganization($organizationId)
            ->whereIn('level_id', $levelIds)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function closureFactsForCourse(string $organizationId, string $courseId): array
    {
        $course = Course::query()
            ->forOrganization($organizationId)
            ->whereKey($courseId)
            ->first();

        if ($course === null) {
            return ['level_id' => null, 'program_id' => null, 'planned_sessions' => null, 'is_active' => false];
        }

        $programId = Level::query()->whereKey($course->level_id)->value('program_id');

        return [
            'level_id' => (string) $course->level_id,
            'program_id' => $programId === null ? null : (string) $programId,
            'planned_sessions' => $course->total_sessions,
            'is_active' => (bool) $course->is_active,
        ];
    }
}
