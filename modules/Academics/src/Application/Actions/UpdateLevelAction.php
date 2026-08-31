<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Events\LevelUpdated;
use Modules\Academics\Domain\Models\Level;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Level $level, array $data, ?string $actorId = null, ?string $reason = null): Level
    {
        if (array_key_exists('code', $data) && (string) $data['code'] !== (string) $level->code) {
            $this->assertCodeAvailable((string) $level->program_id, (string) $data['code'], (string) $level->getKey());
        }

        $data = Arr::except($data, ['program_id', 'organization_id', 'reason']);
        $changedFields = [];
        $trackedFields = array_keys($data);
        $oldValues = Arr::only($level->getAttributes(), $trackedFields);

        $level = $this->transaction->run(function () use ($level, $data, &$changedFields, $trackedFields, $oldValues, $actorId, $reason): Level {
            foreach ($data as $field => $value) {
                if ($level->isFillable($field) && $level->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $level->fill($data);
            $level->save();

            if ($changedFields !== [] && $actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $level->program()->value('organization_id'),
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.level_updated',
                    auditableType: 'levels',
                    auditableId: (string) $level->getKey(),
                    oldValues: $oldValues,
                    newValues: Arr::only($level->getAttributes(), $trackedFields),
                    reason: trim($reason),
                );
            }

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
