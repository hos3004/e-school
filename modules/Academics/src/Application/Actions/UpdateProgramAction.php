<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Events\ProgramUpdated;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data الحقول المسموح تحديثها بعد تحقّق FormRequest
     */
    public function execute(Program $program, array $data, ?string $actorId = null, ?string $reason = null): Program
    {
        if (array_key_exists('code', $data) && (string) $data['code'] !== (string) $program->code) {
            $this->assertCodeAvailable((string) $data['code'], (string) $program->getKey());
        }

        if (array_key_exists('default_rate', $data) && $data['default_rate'] !== null) {
            $this->assertRateValid((int) $data['default_rate'], (string) ($data['currency'] ?? $program->currency));
        }

        $this->assertProgramRules($program, $data);

        $changedFields = [];
        $eligibilityData = is_array($data['eligibility'] ?? null) ? $data['eligibility'] : null;
        $data = Arr::except($data, ['eligibility', 'organization_id', 'reason']);
        $trackedFields = array_keys($data);
        $oldValues = Arr::only($program->getAttributes(), $trackedFields);
        $oldEligibility = $program->eligibility?->getAttributes();

        $program = $this->transaction->run(function () use ($program, $data, $eligibilityData, &$changedFields, $trackedFields, $oldValues, $oldEligibility, $actorId, $reason): Program {
            foreach ($data as $field => $value) {
                if ($program->isFillable($field) && $program->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $program->fill($data);
            $program->save();

            if ($eligibilityData !== null) {
                $program->eligibility()->updateOrCreate(
                    ['program_id' => (string) $program->getKey()],
                    $eligibilityData,
                );
                $changedFields[] = 'eligibility';
            }

            $changedFields = array_values(array_unique($changedFields));

            if ($changedFields !== [] && $actorId !== null && $reason !== null && trim($reason) !== '') {
                $newValues = Arr::only($program->getAttributes(), $trackedFields);
                if ($eligibilityData !== null) {
                    $newValues['eligibility'] = $program->eligibility()->first()?->getAttributes();
                }

                $auditOldValues = $oldValues;
                if ($eligibilityData !== null) {
                    $auditOldValues['eligibility'] = $oldEligibility;
                }

                $this->audit->record(
                    organizationId: (string) $program->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'academics.program_updated',
                    auditableType: 'programs',
                    auditableId: (string) $program->getKey(),
                    oldValues: $auditOldValues,
                    newValues: $newValues,
                    reason: trim($reason),
                );
            }

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

    /** @param array<string, mixed> $data */
    private function assertProgramRules(Program $program, array $data): void
    {
        $type = $data['program_type'] ?? $program->program_type;
        $type = $type instanceof ProgramType ? $type->value : (string) $type;
        $startValue = array_key_exists('start_date', $data) ? $data['start_date'] : $program->start_date?->toDateString();
        $endValue = array_key_exists('end_date', $data) ? $data['end_date'] : $program->end_date?->toDateString();
        $start = $startValue === null ? null : (string) $startValue;
        $end = $endValue === null ? null : (string) $endValue;

        if ($type === ProgramType::FixedDuration->value && ($start === null || $start === '' || $end === null || $end === '')) {
            throw BusinessRuleViolation::make('academics.fixed_program_dates_required', 'academics::errors.fixed_program_dates_required');
        }

        if ($type === ProgramType::Ongoing->value && $end !== null && $end !== '') {
            throw BusinessRuleViolation::make('academics.ongoing_program_end_forbidden', 'academics::errors.ongoing_program_end_forbidden');
        }

        if ($start !== null && $start !== '' && $end !== null && $end !== '' && $end < $start) {
            throw BusinessRuleViolation::make('academics.program_end_before_start', 'academics::errors.program_end_before_start');
        }

        $from = array_key_exists('age_from', $data) ? $data['age_from'] : $program->age_from;
        $to = array_key_exists('age_to', $data) ? $data['age_to'] : $program->age_to;
        if ($from !== null && $to !== null && (int) $to < (int) $from) {
            throw BusinessRuleViolation::make('academics.age_range_invalid', 'academics::errors.age_range_invalid');
        }
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
