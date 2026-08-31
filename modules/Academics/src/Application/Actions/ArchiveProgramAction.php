<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\ProgramArchived;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * أرشفة برنامج أكاديمي.
 *
 * القاعدة: لا يُؤرشف برنامج فيه كورسات نشطة — تُؤرشف الكورسات أولًا.
 * الأرشفة تعليق لا حذف؛ البيانات تبقى والسجل يُوثَّق بسبب مكتوب.
 */
final readonly class ArchiveProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Program $program, string $reason, ?string $actorId = null): Program
    {
        $this->assertReasonGiven($reason);
        $this->assertNoActiveCourses($program);

        $program = $this->transaction->run(function () use ($program, $reason, $actorId): Program {
            $program->delete();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $program->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.program_archived',
                    auditableType: 'programs',
                    auditableId: (string) $program->getKey(),
                    oldValues: ['archived_at' => null],
                    newValues: ['archived_at' => now()->utc()->toIso8601String()],
                    reason: trim($reason),
                );
            }

            return $program;
        });

        $this->events->dispatch(new ProgramArchived(
            programId: (string) $program->getKey(),
            organizationId: (string) $program->organization_id,
            reason: trim($reason),
        ));

        return $program;
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }
    }

    private function assertNoActiveCourses(Program $program): void
    {
        $activeCourses = Course::query()
            ->where('is_active', true)
            ->whereHas('level', fn ($query) => $query->where('levels.program_id', $program->getKey()))
            ->count();

        if ($activeCourses > 0) {
            throw BusinessRuleViolation::make(
                'academics.program_has_active_courses',
                'academics::errors.program_has_active_courses',
                ['code' => (string) $program->code],
            );
        }
    }
}
