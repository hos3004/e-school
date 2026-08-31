<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Queries;

use Modules\Discipline\Domain\Contracts\ReactivationRequestQueries;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\ValueObjects\ReactivationRequestData;

final class ReactivationRequestQueryService implements ReactivationRequestQueries
{
    public function find(string $reactivationRequestId): ?ReactivationRequestData
    {
        $request = ReactivationRequest::query()->find($reactivationRequestId);

        return $request === null ? null : new ReactivationRequestData(
            id: (string) $request->getKey(),
            organizationId: (string) $request->organization_id,
            requestedBy: (string) $request->requested_by,
            canStartAssessment: $request->status === ReactivationStatus::Pending
                && $request->assessment_attempt_id === null,
        );
    }
}
