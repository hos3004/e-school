<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\CourseArchived;
use Modules\Academics\Domain\Models\Course;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * أرشفة كورس — تعليق لا حذف؛ بسبب موثّق يُسجَّل في حدث الأرشفة.
 */
final readonly class ArchiveCourseAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Course $course, string $reason, ?string $actorId = null): Course
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }

        $course = $this->transaction->run(function () use ($course, $reason, $actorId): Course {
            $course->delete();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $course->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.course_archived',
                    auditableType: 'courses',
                    auditableId: (string) $course->getKey(),
                    oldValues: ['archived_at' => null],
                    newValues: ['archived_at' => now()->utc()->toIso8601String()],
                    reason: trim($reason),
                );
            }

            return $course;
        });

        $this->events->dispatch(new CourseArchived(
            courseId: (string) $course->getKey(),
            organizationId: (string) $course->organization_id,
            reason: trim($reason),
        ));

        return $course;
    }
}
