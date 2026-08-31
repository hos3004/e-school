<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Events\LevelCreated;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إضافة مستوى إلى برنامج أكاديمي.
 */
final readonly class CreateLevelAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات المستوى بعد تحقّق FormRequest
     */
    public function execute(array $data, ?string $actorId = null, ?string $reason = null): Level
    {
        $programId = (string) $data['program_id'];
        $code = (string) $data['code'];
        $organizationId = (string) ($data['organization_id'] ?? '');
        if ($organizationId === '') {
            throw BusinessRuleViolation::make('academics.organization_required', 'academics::errors.organization_required');
        }

        $program = $this->assertProgramExists($programId, $organizationId);
        $this->assertCodeAvailable($programId, $code);

        $level = $this->transaction->run(function () use ($data, $program, $actorId, $reason): Level {
            $level = new Level;
            $level->fill(Arr::except($data, ['organization_id', 'reason']));
            $level->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $program->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.level_created',
                    auditableType: 'levels',
                    auditableId: (string) $level->getKey(),
                    oldValues: null,
                    newValues: Arr::only($level->getAttributes(), ['program_id', 'code', 'name', 'sort_order']),
                    reason: trim($reason),
                );
            }

            return $level;
        });

        $this->events->dispatch(new LevelCreated(
            levelId: (string) $level->getKey(),
            programId: (string) $level->program_id,
            code: (string) $level->code,
            name: (array) $level->name,
            sortOrder: (int) $level->sort_order,
        ));

        return $level;
    }

    private function assertProgramExists(string $programId, string $organizationId): Program
    {
        $program = Program::query()->whereKey($programId)->where('organization_id', $organizationId)->first();

        if ($program === null) {
            throw BusinessRuleViolation::make(
                'academics.program_not_found',
                'academics::errors.program_not_found',
                ['program_id' => $programId],
            );
        }

        return $program;
    }

    private function assertCodeAvailable(string $programId, string $code): void
    {
        $exists = Level::query()
            ->where('program_id', $programId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academics.level_code_taken',
                'academics::errors.level_code_taken',
                ['code' => $code],
            );
        }
    }
}
