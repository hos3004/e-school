<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\CourseCreated;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data  بيانات الكورس بعد تحقّق FormRequest
     */
    public function execute(array $data): Course
    {
        $levelId = (string) $data['level_id'];
        $code = (string) $data['code'];

        $this->assertLevelExists($levelId);
        $this->assertCodeAvailable($code);
        $this->assertTotalSessionsValid($data);

        $course = $this->transaction->run(function () use ($data): Course {
            $course = new Course;
            $course->fill($data);
            $course->save();

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

    private function assertLevelExists(string $levelId): void
    {
        $exists = Level::query()->whereKey($levelId)->exists();

        if (! $exists) {
            throw BusinessRuleViolation::make(
                'academics.level_not_found',
                'academics::errors.level_not_found',
                ['level_id' => $levelId],
            );
        }
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
     * @param  array<string, mixed>  $data
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
}
