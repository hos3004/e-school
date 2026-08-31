<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Events\ProgramCreated;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات البرنامج بعد تحقّق FormRequest
     */
    public function execute(array $data, ?string $actorId = null, ?string $reason = null): Program
    {
        $code = (string) $data['code'];
        $this->assertCodeAvailable($code);
        $this->assertRateValid((int) ($data['default_rate'] ?? 0), (string) ($data['currency'] ?? 'EGP'));
        $this->assertProgramRules($data);

        $program = $this->transaction->run(function () use ($data, $actorId, $reason): Program {
            $eligibility = is_array($data['eligibility'] ?? null) ? $data['eligibility'] : null;
            $program = new Program;
            $program->fill(Arr::except($data, ['eligibility', 'reason']));
            $program->save();

            if ($eligibility !== null) {
                $program->eligibility()->create($eligibility);
            }

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $program->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.program_created',
                    auditableType: 'programs',
                    auditableId: (string) $program->getKey(),
                    oldValues: null,
                    newValues: Arr::only($program->getAttributes(), [
                        'code', 'name', 'program_type', 'is_active', 'default_session_minutes',
                    ]),
                    reason: trim($reason),
                );
            }

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

    /** @param array<string, mixed> $data */
    private function assertProgramRules(array $data): void
    {
        $type = $data['program_type'] ?? ProgramType::Ongoing->value;
        $type = $type instanceof ProgramType ? $type->value : (string) $type;
        $start = isset($data['start_date']) ? (string) $data['start_date'] : null;
        $end = isset($data['end_date']) ? (string) $data['end_date'] : null;

        if ($type === ProgramType::FixedDuration->value && ($start === null || $start === '' || $end === null || $end === '')) {
            throw BusinessRuleViolation::make(
                'academics.fixed_program_dates_required',
                'academics::errors.fixed_program_dates_required',
            );
        }

        if ($type === ProgramType::Ongoing->value && $end !== null && $end !== '') {
            throw BusinessRuleViolation::make(
                'academics.ongoing_program_end_forbidden',
                'academics::errors.ongoing_program_end_forbidden',
            );
        }

        if ($start !== null && $start !== '' && $end !== null && $end !== '' && $end < $start) {
            throw BusinessRuleViolation::make(
                'academics.program_end_before_start',
                'academics::errors.program_end_before_start',
            );
        }

        $from = $data['age_from'] ?? null;
        $to = $data['age_to'] ?? null;
        if ($from !== null && $to !== null && (int) $to < (int) $from) {
            throw BusinessRuleViolation::make(
                'academics.age_range_invalid',
                'academics::errors.age_range_invalid',
            );
        }
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
