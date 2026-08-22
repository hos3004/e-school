<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\LevelCreated;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data  بيانات المستوى بعد تحقّق FormRequest
     */
    public function execute(array $data): Level
    {
        $programId = (string) $data['program_id'];
        $code = (string) $data['code'];

        $this->assertProgramExists($programId);
        $this->assertCodeAvailable($programId, $code);

        $level = $this->transaction->run(function () use ($data): Level {
            $level = new Level;
            $level->fill($data);
            $level->save();

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

    private function assertProgramExists(string $programId): void
    {
        $exists = Program::query()->whereKey($programId)->exists();

        if (! $exists) {
            throw BusinessRuleViolation::make(
                'academics.program_not_found',
                'academics::errors.program_not_found',
                ['program_id' => $programId],
            );
        }
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
