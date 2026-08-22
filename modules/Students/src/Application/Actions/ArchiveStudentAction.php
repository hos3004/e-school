<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Events\StudentArchived;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * أرشفة طالب: تعليق وصوله دون حذف بياناته (SoftDeletes) مع تسجيل السبب
 * في حدث الأرشفة ليتولّى مستمع التدقيق توثيقه.
 */
final readonly class ArchiveStudentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(StudentProfile $student, string $reason): StudentProfile
    {
        $this->assertReasonGiven($reason);
        $this->assertNotArchived($student);

        $this->transaction->run(function () use ($student): void {
            $student->delete();
        });

        $this->events->dispatch(new StudentArchived(
            studentId: (string) $student->getKey(),
            organizationId: (string) $student->organization_id,
            reason: trim($reason),
        ));

        return $student;
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'students.reason_required',
                'students::errors.archive_reason_required',
            );
        }
    }

    private function assertNotArchived(StudentProfile $student): void
    {
        if ($student->trashed()) {
            throw BusinessRuleViolation::make(
                'students.already_archived',
                'students::errors.already_archived',
                ['student_id' => $student->getKey()],
            );
        }
    }
}
