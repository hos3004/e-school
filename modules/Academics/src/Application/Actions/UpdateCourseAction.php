<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Events\CourseUpdated;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات كورس قائم.
 */
final readonly class UpdateCourseAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Course $course, array $data, ?string $actorId = null, ?string $reason = null): Course
    {
        if (array_key_exists('code', $data) && (string) $data['code'] !== (string) $course->code) {
            $this->assertCodeAvailable((string) $data['code'], (string) $course->getKey());
        }

        if (array_key_exists('total_sessions', $data) && $data['total_sessions'] !== null && (int) $data['total_sessions'] < 1) {
            throw BusinessRuleViolation::make(
                'academics.total_sessions_invalid',
                'academics::errors.total_sessions_invalid',
            );
        }

        $levelId = (string) ($data['level_id'] ?? $course->level_id);
        $level = Level::query()
            ->whereKey($levelId)
            ->whereHas('program', static fn ($query) => $query->where('organization_id', (string) $course->organization_id))
            ->first();
        if ($level === null) {
            throw BusinessRuleViolation::make('academics.level_not_found', 'academics::errors.level_not_found', ['level_id' => $levelId]);
        }

        $categoryIds = $this->validatedCategoryIds(
            $data,
            (string) $course->organization_id,
            (string) $level->program_id,
        );

        $changedFields = [];
        $data = Arr::except($data, ['organization_id', 'category_ids', 'reason']);
        $trackedFields = array_keys($data);
        $oldValues = Arr::only($course->getAttributes(), $trackedFields);
        $oldCategoryIds = $course->categories()->pluck('program_categories.id')->map(static fn (mixed $id): string => (string) $id)->all();

        $course = $this->transaction->run(function () use ($course, $data, $categoryIds, &$changedFields, $trackedFields, $oldValues, $oldCategoryIds, $actorId, $reason): Course {
            foreach ($data as $field => $value) {
                if ($course->isFillable($field) && $course->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $course->fill($data);
            $course->save();

            if ($categoryIds !== null && $categoryIds !== $oldCategoryIds) {
                $course->categories()->sync($categoryIds);
                $changedFields[] = 'category_ids';
            }

            $changedFields = array_values(array_unique($changedFields));

            if ($changedFields !== [] && $actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $course->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.course_updated',
                    auditableType: 'courses',
                    auditableId: (string) $course->getKey(),
                    oldValues: [...$oldValues, 'category_ids' => $oldCategoryIds],
                    newValues: [...Arr::only($course->getAttributes(), $trackedFields), 'category_ids' => $categoryIds ?? $oldCategoryIds],
                    reason: trim($reason),
                );
            }

            return $course;
        });

        if ($changedFields !== []) {
            $this->events->dispatch(new CourseUpdated(
                courseId: (string) $course->getKey(),
                organizationId: (string) $course->organization_id,
                changedFields: $changedFields,
            ));
        }

        return $course;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>|null
     */
    private function validatedCategoryIds(array $data, string $organizationId, string $programId): ?array
    {
        if (!array_key_exists('category_ids', $data)) {
            return null;
        }

        $ids = array_values(array_unique(array_map('strval', (array) $data['category_ids'])));
        if ($ids === []) {
            return [];
        }

        $count = ProgramCategory::query()
            ->where('organization_id', $organizationId)
            ->where(static fn ($query) => $query
                ->whereNull('program_id')
                ->orWhere('program_id', $programId))
            ->whereKey($ids)
            ->count();

        if ($count !== count($ids)) {
            throw BusinessRuleViolation::make('academics.category_outside_course_program', 'academics::errors.category_outside_course_program');
        }

        return $ids;
    }

    private function assertCodeAvailable(string $code, string $exceptId): void
    {
        $exists = Course::query()
            ->withTrashed()
            ->where('code', $code)
            ->whereKeyNot($exceptId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academics.course_code_taken',
                'academics::errors.course_code_taken',
                ['code' => $code],
            );
        }
    }
}
