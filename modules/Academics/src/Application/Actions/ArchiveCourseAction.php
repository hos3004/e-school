<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\CourseArchived;
use Modules\Academics\Domain\Models\Course;
use Shared\Support\Transaction;

/**
 * أرشفة كورس — تعليق لا حذف؛ بسبب موثّق يُسجَّل في حدث الأرشفة.
 */
final readonly class ArchiveCourseAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Course $course, string $reason): Course
    {
        $course = $this->transaction->run(function () use ($course): Course {
            $course->delete();

            return $course;
        });

        $this->events->dispatch(new CourseArchived(
            courseId: (string) $course->getKey(),
            organizationId: (string) $course->organization_id,
            reason: $reason,
        ));

        return $course;
    }
}
