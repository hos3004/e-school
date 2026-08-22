<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\LevelUpdated;
use Modules\Academics\Domain\Models\Level;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات مستوى قائم.
 */
final readonly class UpdateLevelAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Level $level, array $data): Level
    {
        if (array_key_exists('code', $data) && (string) $data['code'] !== (string) $level->code) {
            $this->assertCodeAvailable((string) $level->program_id, (string) $data['code'], (string) $level->getKey());
        }

        $changedFields = [];

        $level = $this->transaction->run(function () use ($level, $data, &$changedFields): Level {
            foreach ($data as $field => $value) {
                if ($level->isFillable($field) && $level->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $level->fill($data);
            $level->save();

            return $level;
        });

        if ($changedFields !== []) {
            $this->events->dispatch(new LevelUpdated(
                levelId: (string) $level->getKey(),
                programId: (string) $level->program_id,
                changedFields: $changedFields,
            ));
        }

        return $level;
    }

    private function assertCodeAvailable(string $programId, string $code, string $exceptId): void
    {
        $exists = Level::query()
            ->where('program_id', $programId)
            ->where('code', $code)
            ->whereKeyNot($exceptId)
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
