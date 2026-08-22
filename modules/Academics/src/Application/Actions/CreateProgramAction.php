<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\ProgramCreated;
use Modules\Academics\Domain\Models\Program;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

/**
 * إنشاء برنامج أكاديمي جديد داخل مؤسسة.
 */
final readonly class CreateProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات البرنامج بعد تحقّق FormRequest
     */
    public function execute(array $data): Program
    {
        $code = (string) $data['code'];
        $this->assertCodeAvailable($code);
        $this->assertRateValid((int) ($data['default_rate'] ?? 0), (string) ($data['currency'] ?? 'EGP'));

        $program = $this->transaction->run(function () use ($data): Program {
            $program = new Program;
            $program->fill($data);
            $program->save();

            return $program;
        });

        $this->events->dispatch(new ProgramCreated(
            programId: (string) $program->getKey(),
            organizationId: (string) $program->organization_id,
            code: (string) $program->code,
            name: (array) $program->name,
            durationWeeks: $program->duration_weeks !== null ? (int) $program->duration_weeks : null,
            defaultSessionMinutes: (int) $program->default_session_minutes,
            defaultRate: $program->default_rate !== null ? (int) $program->default_rate : null,
            currency: (string) $program->currency,
        ));

        return $program;
    }

    private function assertCodeAvailable(string $code): void
    {
        $exists = Program::query()
            ->withTrashed()
            ->where('code', $code)
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
