<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Contracts;

use Modules\Assessments\Domain\ValueObjects\ReactivationAssessmentData;

interface ReactivationAssessmentQueries
{
    /** @return list<ReactivationAssessmentData> */
    public function forRequest(string $organizationId, string $studentProfileId, string $requestId): array;
}
