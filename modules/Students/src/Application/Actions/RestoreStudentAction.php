<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Events\StudentRestored;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * استرجاع طالب مؤرشف — إلغاء الأرشفة دون مسّ تاريخه.
 */
final readonly class RestoreStudentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(string $studentId): StudentProfile
    {
        /** @var StudentProfile|null $student */
        $student = StudentProfile::query()->withTrashed()->find($studentId);

        if ($student === null) {
            throw BusinessRuleViolation::make(
                'students.not_found',
                'students::errors.not_found',
                ['student_id' => $studentId],
            );
        }

        if (!$student->trashed()) {
            throw BusinessRuleViolation::make(
                'students.not_archived',
                'students::errors.not_archived',
                ['student_id' => $studentId],
            );
        }

        $this->transaction->run(function () use ($student): void {
            $student->restore();
        });

        $this->events->dispatch(new StudentRestored(
            studentId: (string) $student->getKey(),
            organizationId: (string) $student->organization_id,
        ));

        return $student;
    }
}
