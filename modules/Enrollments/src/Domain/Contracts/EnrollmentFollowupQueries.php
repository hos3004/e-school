<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Contracts;

use Modules\Enrollments\Domain\ValueObjects\FollowupEnrollmentData;

interface EnrollmentFollowupQueries
{
    /** @return list<FollowupEnrollmentData> */
    public function forOrganization(string $organizationId): array;

    /** @return list<array{id: string, from: ?string, to: string, reason: string, actor_id: string, at: ?string}> */
    public function history(string $organizationId, string $enrollmentId): array;
}
