<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Events\CourseCreated;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء كورس جديد داخل مستوى.
 */
final readonly class CreateCourseAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات الكورس بعد تحقّق FormRequest
     */
    public function execute(array $data, ?string $actorId = null, ?string $reason = null): Course
    {
        $levelId = (string) $data['level_id'];
        $code = (string) $data['code'];
        $organizationId = (string) $data['organization_id'];

        $level = $this->assertLevelBelongsToOrganization($levelId, $organizationId);
        $this->assertCodeAvailable($code);
        $this->assertTotalSessionsValid($data);
        $categoryIds = $this->validatedCategoryIds($data, $organizationId, (string) $level->program_id);

        $course = $this->transaction->run(function () use ($data, $categoryIds, $actorId, $reason): Course {
            $course = new Course;
            $course->fill(Arr::except($data, ['category_ids', 'reason']));
            $course->save();

            if ($categoryIds !== []) {
                $course->categories()->sync($categoryIds);
            }

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $course->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.course_created',
                    auditableType: 'courses',
                    auditableId: (string) $course->getKey(),
                    oldValues: null,
                    newValues: [
                        'code' => $course->code,
                        'level_id' => $course->level_id,
                        'is_active' => $course->is_active,
                        'category_ids' => $categoryIds,
                    ],
                    reason: trim($reason),
                );
            }

            return $course;
        });

        $this->events->dispatch(new CourseCreated(
            courseId: (string) $course->getKey(),
            organizationId: (string) $course->organization_id,
            levelId: (string) $course->level_id,
            code: (string) $course->code,
            name: (array) $course->name,
            totalSessions: $course->total_sessions !== null ? (int) $course->total_sessions : null,
        ));

        return $course;
    }

    private function assertLevelBelongsToOrganization(string $levelId, string $organizationId): Level
    {
        $level = Level::query()
            ->whereKey($levelId)
            ->whereHas('program', static fn ($query) => $query->where('organization_id', $organizationId))
            ->first();

        if ($level === null) {
            throw BusinessRuleViolation::make(
                'academics.level_not_found',
                'academics::errors.level_not_found',
                ['level_id' => $levelId],
            );
        }

        return $level;
    }

    private function assertCodeAvailable(string $code): void
    {
        $exists = Course::query()
            ->withTrashed()
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academics.course_code_taken',
                'academics::errors.course_code_taken',
                ['code' => $code],
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertTotalSessionsValid(array $data): void
    {
        if (array_key_exists('total_sessions', $data) && $data['total_sessions'] !== null) {
            if ((int) $data['total_sessions'] < 1) {
                throw BusinessRuleViolation::make(
                    'academics.total_sessions_invalid',
                    'academics::errors.total_sessions_invalid',
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function validatedCategoryIds(array $data, string $organizationId, string $programId): array
    {
        $ids = array_values(array_unique(array_map('strval', (array) ($data['category_ids'] ?? []))));
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
}
