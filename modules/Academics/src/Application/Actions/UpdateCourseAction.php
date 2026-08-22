<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\CourseUpdated;
use Modules\Academics\Domain\Models\Course;
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
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Course $course, array $data): Course
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

        $changedFields = [];

        $course = $this->transaction->run(function () use ($course, $data, &$changedFields): Course {
            foreach ($data as $field => $value) {
                if ($course->isFillable($field) && $course->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $course->fill($data);
            $course->save();

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
