<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Queries;

use Modules\Enrollments\Domain\Contracts\EnrollmentFollowupQueries;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Modules\Enrollments\Domain\ValueObjects\FollowupEnrollmentData;

final readonly class EnrollmentFollowupQueryService implements EnrollmentFollowupQueries
{
    public function forOrganization(string $organizationId): array
    {
        return Enrollment::query()->forOrganization($organizationId)->orderByDesc('created_at')->get()
            ->map(static fn (Enrollment $row): FollowupEnrollmentData => new FollowupEnrollmentData(
                (string) $row->id, (string) $row->student_profile_id, (string) $row->program_id, $row->status->value,
                $row->expected_return_date?->toDateString(), $row->frozen_reason,
            ))->all();
    }

    public function history(string $organizationId, string $enrollmentId): array
    {
        if (!Enrollment::query()->forOrganization($organizationId)->whereKey($enrollmentId)->exists()) {
            return [];
        }

        return EnrollmentStatusHistory::query()->where('enrollment_id', $enrollmentId)->orderByDesc('changed_at')->get()
            ->map(static fn (EnrollmentStatusHistory $row): array => [
                'id' => (string) $row->id, 'from' => $row->from_status, 'to' => $row->to_status,
                'reason' => $row->reason, 'actor_id' => $row->changed_by, 'at' => $row->changed_at?->toIso8601String(),
            ])->all();
    }
}
