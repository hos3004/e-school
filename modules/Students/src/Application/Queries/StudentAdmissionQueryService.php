<?php

declare(strict_types=1);

namespace Modules\Students\Application\Queries;

use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\ValueObjects\AdmissionCandidateData;

final readonly class StudentAdmissionQueryService implements StudentAdmissionQueries
{
    public function isClearedForAssignment(string $studentProfileId): bool
    {
        return RegistrationApplication::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereIn('status', self::clearedStatusValues())
            ->exists();
    }

    public function placementCandidates(string $organizationId, array $applicationIds): array
    {
        $applicationIds = array_values(array_unique(array_filter(
            $applicationIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($organizationId === '' || $applicationIds === []) {
            return [];
        }

        return RegistrationApplication::query()
            ->forOrganization($organizationId)
            ->whereKey($applicationIds)
            ->with('studentProfile:id,student_code')
            ->orderBy('full_name')
            ->get()
            ->map(static fn (RegistrationApplication $application): AdmissionCandidateData => new AdmissionCandidateData(
                applicationId: (string) $application->getKey(),
                organizationId: (string) $application->organization_id,
                studentProfileId: $application->student_profile_id === null
                    ? null
                    : (string) $application->student_profile_id,
                fullName: (string) $application->full_name,
                studentCode: $application->studentProfile?->student_code === null
                    ? null
                    : (string) $application->studentProfile->student_code,
                status: $application->status->value,
                clearedForAssignment: $application->status->isClearedForAssignment(),
                preferredProgramId: $application->preferred_program_id === null
                    ? null
                    : (string) $application->preferred_program_id,
                preferredCourseId: $application->preferred_course_id === null
                    ? null
                    : (string) $application->preferred_course_id,
            ))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function clearedStatusValues(): array
    {
        return array_values(array_map(
            static fn (RegistrationStatus $status): string => $status->value,
            array_filter(
                RegistrationStatus::cases(),
                static fn (RegistrationStatus $status): bool => $status->isClearedForAssignment(),
            ),
        ));
    }
}
