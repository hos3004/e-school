<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Shared\Support\LocalizedJsonColumn;

/** قراءات لوحة الإدارة داخل حدود Academics فقط. */
final readonly class AcademicAdministrationQueryService
{
    public function __construct(private GeographyQueries $geography) {}

    /** @return array<string, mixed> */
    public function programHub(string $organizationId, string $programId): array
    {
        $program = Program::query()
            ->where('organization_id', $organizationId)
            ->whereKey($programId)
            ->with([
                'eligibility',
                'levels' => static fn ($query) => $query->orderBy('sort_order'),
                'levels.courses' => static fn ($query) => $query->orderBy('code'),
            ])
            ->first();

        if ($program === null) {
            return ['levels' => [], 'categories' => [], 'eligibility' => []];
        }

        $levels = $program->levels->map(static fn (Level $level): array => [
            'id' => (string) $level->getKey(),
            'code' => (string) $level->code,
            'name' => LocalizedJsonColumn::display($level->name),
            'sort_order' => (int) $level->sort_order,
            'courses_count' => $level->courses->count(),
            'active_courses_count' => $level->courses->where('is_active', true)->count(),
            'courses' => $level->courses->map(static fn (Course $course): array => [
                'id' => (string) $course->getKey(),
                'code' => (string) $course->code,
                'name' => LocalizedJsonColumn::display($course->name),
                'session_mode' => $course->session_mode->value,
                'is_active' => (bool) $course->is_active,
                'total_sessions' => $course->total_sessions,
            ])->values()->all(),
        ])->values()->all();

        $categories = ProgramCategory::query()
            ->where('organization_id', $organizationId)
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('program_id')
                ->orWhere('program_id', $programId))
            ->withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(static fn (ProgramCategory $category): array => [
                'id' => (string) $category->getKey(),
                'code' => (string) $category->code,
                'name' => LocalizedJsonColumn::display($category->name),
                'parent_id' => $category->parent_id === null ? null : (string) $category->parent_id,
                'scope' => $category->program_id === null ? 'organization' : 'program',
                'is_active' => (bool) $category->is_active,
                'courses_count' => (int) $category->courses_count,
            ])->values()->all();

        $eligibility = $program->eligibility;
        $countries = collect($this->geography->countries())->keyBy('id');
        $countryIds = $eligibility === null ? [] : $eligibility->countries;
        $regionIds = $eligibility === null ? [] : $eligibility->regions;
        $regionLabels = collect($countries)
            ->flatMap(fn ($country): array => $this->geography->regionsOf($country->id))
            ->whereIn('id', $regionIds)
            ->map(static fn ($region): string => LocalizedJsonColumn::display($region->name))
            ->values()
            ->all();

        return [
            'levels' => $levels,
            'categories' => $categories,
            'eligibility' => $eligibility === null ? [] : [
                'countries' => $countries->whereIn('id', $countryIds)
                    ->map(static fn ($country): string => LocalizedJsonColumn::display($country->name))->values()->all(),
                'regions' => $regionLabels,
                'age_from' => $eligibility->age_from,
                'age_to' => $eligibility->age_to,
                'gender' => $eligibility->gender?->value,
                'manual_approval_required' => (bool) $eligibility->manual_approval_required,
                'teacher_gender_rule' => (string) $eligibility->teacher_gender_rule,
                'requires_individual_sessions' => (bool) $eligibility->requires_individual_sessions,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function levelHub(string $organizationId, string $levelId): array
    {
        $level = Level::query()
            ->whereKey($levelId)
            ->whereHas('program', static fn (Builder $query): Builder => $query->where('organization_id', $organizationId))
            ->with(['program', 'courses' => static fn ($query) => $query->orderBy('code')])
            ->first();

        if ($level === null) {
            return ['program' => [], 'courses' => []];
        }

        return [
            'program' => [
                'id' => (string) $level->program?->getKey(),
                'code' => (string) $level->program?->code,
                'name' => LocalizedJsonColumn::display($level->program?->name),
            ],
            'courses' => $level->courses->map(static fn (Course $course): array => [
                'id' => (string) $course->getKey(),
                'code' => (string) $course->code,
                'name' => LocalizedJsonColumn::display($course->name),
                'session_mode' => $course->session_mode->value,
                'total_sessions' => $course->total_sessions,
                'is_active' => (bool) $course->is_active,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function courseHub(string $organizationId, string $courseId): array
    {
        $course = Course::query()
            ->where('organization_id', $organizationId)
            ->whereKey($courseId)
            ->with(['level.program', 'categories'])
            ->first();

        if ($course === null) {
            return ['academic_path' => [], 'categories' => []];
        }

        return [
            'academic_path' => [
                'program' => LocalizedJsonColumn::display($course->level?->program?->name),
                'program_code' => (string) $course->level?->program?->code,
                'level' => LocalizedJsonColumn::display($course->level?->name),
                'level_code' => (string) $course->level?->code,
            ],
            'categories' => $course->categories->map(static fn (ProgramCategory $category): array => [
                'id' => (string) $category->getKey(),
                'code' => (string) $category->code,
                'name' => LocalizedJsonColumn::display($category->name),
            ])->values()->all(),
        ];
    }

    /** @return array<string, string> */
    public function categoryOptions(string $organizationId, ?string $programId = null): array
    {
        return ProgramCategory::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->when($programId !== null, static fn (Builder $query): Builder => $query
                ->where(static fn (Builder $scope): Builder => $scope
                    ->whereNull('program_id')
                    ->orWhere('program_id', $programId)))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(static fn (ProgramCategory $category): array => [
                (string) $category->getKey() => sprintf('%s — %s', $category->code, LocalizedJsonColumn::display($category->name)),
            ])
            ->all();
    }
}
