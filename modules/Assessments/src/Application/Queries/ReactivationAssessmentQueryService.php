<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Queries;

use Modules\Assessments\Domain\Contracts\ReactivationAssessmentQueries;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\ValueObjects\ReactivationAssessmentData;

final readonly class ReactivationAssessmentQueryService implements ReactivationAssessmentQueries
{
    public function forRequest(string $organizationId, string $studentProfileId, string $requestId): array
    {
        return AssessmentAttempt::query()->forStudent($studentProfileId)->where('reactivation_request_id', $requestId)
            ->whereHas('assessment', static fn ($query) => $query->where('organization_id', $organizationId)->where('type', AssessmentType::Reactivation->value))
            ->with('assessment')->orderByDesc('started_at')->get()
            ->map(static fn (AssessmentAttempt $row): ReactivationAssessmentData => new ReactivationAssessmentData(
                (string) $row->id, $row->assessment->title, $row->score, $row->assessment->total_score, $row->passed, $row->graded_at?->toIso8601String(),
            ))->all();
    }
}
