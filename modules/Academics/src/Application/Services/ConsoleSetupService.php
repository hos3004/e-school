<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Application\Actions\CreateLevelAction;
use Modules\Academics\Application\Actions\CreateProgramAction;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Application\Actions\UpdateLevelAction;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Shared\Support\LocalizedJsonColumn;

/** Console composition boundary: identifiers and arrays only; existing Actions own writes. */
final readonly class ConsoleSetupService
{
    public function __construct(
        private CreateProgramAction $createProgram,
        private UpdateProgramAction $updateProgram,
        private CreateLevelAction $createLevel,
        private UpdateLevelAction $updateLevel,
        private CreateCourseAction $createCourse,
        private UpdateCourseAction $updateCourse,
    ) {}

    /** @return array{programs: list<array<string, mixed>>, levels: list<array<string, mixed>>, courses: list<array<string, mixed>>} */
    public function catalog(string $organizationId): array
    {
        $programs = Program::query()->forOrganization($organizationId)
            ->with(['levels' => fn ($query) => $query->orderBy('sort_order'), 'levels.courses.level'])
            ->orderBy('sort_order')->orderBy('code')->get();
        $levels = $programs->flatMap(fn (Program $program) => $program->levels);
        $courses = $levels->flatMap(fn (Level $level) => $level->courses);

        return [
            'programs' => $programs->map(fn (Program $item): array => [
                ...$item->only(['id', 'code', 'name', 'description', 'program_type', 'start_date', 'end_date',
                    'default_session_minutes', 'default_rate', 'currency', 'duration_weeks', 'is_active',
                    'target_gender', 'age_from', 'age_to', 'language', 'sort_order']),
                'label' => LocalizedJsonColumn::display($item->name),
                'start_date' => $item->start_date?->toDateString(),
                'end_date' => $item->end_date?->toDateString(),
            ])->values()->all(),
            'levels' => $levels->map(fn (Level $item): array => [
                ...$item->only(['id', 'program_id', 'code', 'name', 'sort_order']),
                'label' => LocalizedJsonColumn::display($item->name),
            ])->values()->all(),
            'courses' => $courses->map(fn (Course $item): array => [
                ...$item->only(['id', 'level_id', 'code', 'name', 'description', 'total_sessions', 'is_active',
                    'session_mode', 'target_gender', 'age_from', 'age_to', 'default_duration_minutes', 'sessions_per_week']),
                'program_id' => (string) $item->level->program_id,
                'label' => LocalizedJsonColumn::display($item->name),
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveProgram(string $organizationId, ?string $id, array $data, string $actorId, string $reason): string
    {
        $program = $id === null ? null : Program::query()->forOrganization($organizationId)->findOrFail($id);
        Gate::authorize($program === null ? 'create' : 'update', $program ?? Program::class);
        if ($program !== null) {
            $data = $this->preserveTranslations($data, $program->only(['name', 'description']));
        }
        $saved = $program === null
            ? $this->createProgram->execute([...$data, 'organization_id' => $organizationId], $actorId, $reason)
            : $this->updateProgram->execute($program, $data, $actorId, $reason);

        return (string) $saved->getKey();
    }

    /** @param array<string, mixed> $data */
    public function saveLevel(string $organizationId, ?string $id, array $data, string $actorId, string $reason): string
    {
        $level = $id === null ? null : Level::query()->whereHas('program', fn ($query) => $query->where('organization_id', $organizationId))->findOrFail($id);
        Gate::authorize($level === null ? 'create' : 'update', $level ?? Level::class);
        if ($level !== null) {
            $data = $this->preserveTranslations($data, $level->only(['name']));
        }
        $saved = $level === null
            ? $this->createLevel->execute([...$data, 'organization_id' => $organizationId], $actorId, $reason)
            : $this->updateLevel->execute($level, $data, $actorId, $reason);

        return (string) $saved->getKey();
    }

    /** @param array<string, mixed> $data */
    public function saveCourse(string $organizationId, ?string $id, array $data, string $actorId, string $reason): string
    {
        $course = $id === null ? null : Course::query()->where('organization_id', $organizationId)->findOrFail($id);
        Gate::authorize($course === null ? 'create' : 'update', $course ?? Course::class);
        if ($course !== null) {
            $data = $this->preserveTranslations($data, $course->only(['name', 'description']));
        }
        $saved = $course === null
            ? $this->createCourse->execute([...$data, 'organization_id' => $organizationId], $actorId, $reason)
            : $this->updateCourse->execute($course, $data, $actorId, $reason);

        return (string) $saved->getKey();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function preserveTranslations(array $data, array $existing): array
    {
        foreach (['name', 'description'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_replace(is_array($existing[$field] ?? null) ? $existing[$field] : [], $data[$field]);
            }
        }

        return $data;
    }
}
