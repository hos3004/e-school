<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\ProgramUpdated;
use Modules\Academics\Domain\Models\Program;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

/**
 * تحديث بيانات برنامج أكاديمي قائم.
 */
final readonly class UpdateProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Program $program, array $data): Program
    {
        if (array_key_exists('code', $data) && (string) $data['code'] !== (string) $program->code) {
            $this->assertCodeAvailable((string) $data['code'], (string) $program->getKey());
        }

        if (array_key_exists('default_rate', $data) && $data['default_rate'] !== null) {
            $this->assertRateValid((int) $data['default_rate'], (string) ($data['currency'] ?? $program->currency));
        }

        $changedFields = [];

        $program = $this->transaction->run(function () use ($program, $data, &$changedFields): Program {
            foreach ($data as $field => $value) {
                if ($program->isFillable($field) && $program->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $program->fill($data);
            $program->save();

            return $program;
        });

        if ($changedFields !== []) {
            $this->events->dispatch(new ProgramUpdated(
                programId: (string) $program->getKey(),
                organizationId: (string) $program->organization_id,
                changedFields: $changedFields,
            ));
        }

        return $program;
    }

    private function assertCodeAvailable(string $code, string $exceptId): void
    {
        $exists = Program::query()
            ->withTrashed()
            ->where('code', $code)
            ->whereKeyNot($exceptId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academics.program_code_taken',
                'academics::errors.program_code_taken',
                ['code' => $code],
            );
        }
    }

    private function assertRateValid(int $rateMinorUnits, string $currency): void
    {
        $rate = Money::of($rateMinorUnits, $currency);

        if ($rate->isNegative()) {
            throw BusinessRuleViolation::make(
                'academics.rate_negative',
                'academics::errors.rate_negative',
            );
        }
    }
}
