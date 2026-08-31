<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Queries;

use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentSummaryData;

final readonly class EnrollmentAdministrationQueryService implements EnrollmentAdministrationQueries
{
    public function forStudent(string $organizationId, string $studentProfileId): array
    {
        return Enrollment::query()
            ->forOrganization($organizationId)
            ->forStudent($studentProfileId)
            ->latest('created_at')
            ->get()
            ->map(static fn (Enrollment $enrollment): EnrollmentSummaryData => new EnrollmentSummaryData(
                id: (string) $enrollment->getKey(),
                programId: (string) $enrollment->program_id,
                status: $enrollment->status->value,
                currentLevelId: $enrollment->current_level_id === null ? null : (string) $enrollment->current_level_id,
                appliedAt: $enrollment->applied_at?->toIso8601String(),
                activatedAt: $enrollment->activated_at?->toIso8601String(),
                completedAt: $enrollment->completed_at?->toIso8601String(),
                expectedReturnDate: $enrollment->expected_return_date?->toDateString(),
            ))
            ->values()
            ->all();
    }

    public function schedulableEnrollmentIdsByStudent(
        string $organizationId,
        string $programId,
        array $studentProfileIds = [],
    ): array {
        $studentProfileIds = array_values(array_unique(array_filter(
            $studentProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        return Enrollment::query()
            ->forOrganization($organizationId)
            ->where('program_id', $programId)
            ->whereIn('status', [
                EnrollmentStatus::Approved->value,
                EnrollmentStatus::Active->value,
            ])
            ->when(
                $studentProfileIds !== [],
                static fn ($query) => $query->whereIn('student_profile_id', $studentProfileIds),
            )
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->get(['id', 'student_profile_id'])
            ->mapWithKeys(static fn (Enrollment $enrollment): array => [
                (string) $enrollment->student_profile_id => (string) $enrollment->getKey(),
            ])
            ->all();
    }
}
