<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Queries;

use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherRate;
use Modules\Staff\Domain\ValueObjects\TeacherAvailabilityData;
use Modules\Staff\Domain\ValueObjects\TeacherContractData;
use Modules\Staff\Domain\ValueObjects\TeacherRateData;

final readonly class StaffAdministrationQueryService implements StaffAdministrationQueries
{
    public function availabilityForTeacher(string $organizationId, string $staffProfileId): array
    {
        if (!$this->belongsToOrganization($organizationId, $staffProfileId)) {
            return [];
        }

        return TeacherAvailability::query()
            ->forProfile($staffProfileId)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(static fn (TeacherAvailability $availability): TeacherAvailabilityData => new TeacherAvailabilityData(
                id: (string) $availability->getKey(),
                weekday: (int) $availability->weekday,
                startTime: (string) $availability->start_time,
                endTime: (string) $availability->end_time,
                timezone: (string) $availability->timezone,
                approvalStatus: $availability->approval_status->value,
                decisionReason: $availability->decision_reason,
                effectiveFrom: $availability->effective_from->toDateString(),
                effectiveTo: $availability->effective_to?->toDateString(),
            ))
            ->values()
            ->all();
    }

    public function contractsForTeacher(string $organizationId, string $staffProfileId): array
    {
        if (!$this->belongsToOrganization($organizationId, $staffProfileId)) {
            return [];
        }

        return TeacherContract::query()
            ->forOrganization($organizationId)
            ->forProfile($staffProfileId)
            ->orderByDesc('effective_from')
            ->get()
            ->map(static fn (TeacherContract $contract): TeacherContractData => new TeacherContractData(
                id: (string) $contract->getKey(),
                basis: $contract->basis->value,
                effectiveFrom: $contract->effective_from->toDateString(),
                effectiveTo: $contract->effective_to?->toDateString(),
                baseAmountMajor: $contract->baseMoney()?->toMajor(),
                currency: $contract->currency,
                monthlyTargetSessions: $contract->monthly_target_sessions,
                targetAdminTasks: $contract->target_admin_tasks,
                targetTrainingSessions: $contract->target_training_sessions,
            ))
            ->values()
            ->all();
    }

    public function ratesForTeacher(string $organizationId, string $staffProfileId): array
    {
        if (!$this->belongsToOrganization($organizationId, $staffProfileId)) {
            return [];
        }

        $contractIds = TeacherContract::query()
            ->forOrganization($organizationId)
            ->forProfile($staffProfileId)
            ->pluck('id');

        return TeacherRate::query()
            ->whereIn('teacher_contract_id', $contractIds)
            ->orderByDesc('effective_from')
            ->get()
            ->map(static fn (TeacherRate $rate): TeacherRateData => new TeacherRateData(
                id: (string) $rate->getKey(),
                contractId: (string) $rate->teacher_contract_id,
                scope: $rate->scope->value,
                amountMajor: $rate->money()->toMajor(),
                currency: (string) $rate->currency,
                effectiveFrom: $rate->effective_from->toDateString(),
                effectiveTo: $rate->effective_to?->toDateString(),
                programId: $rate->program_id,
                courseId: $rate->course_id,
                sessionType: $rate->session_type,
            ))
            ->values()
            ->all();
    }

    private function belongsToOrganization(string $organizationId, string $staffProfileId): bool
    {
        return StaffProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($staffProfileId)
            ->exists();
    }
}
