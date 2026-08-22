<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Shared\Domain\DomainEvent;

/**
 * حُسم طلب إعادة التفعيل: قبول أو رفض.
 *
 * عند القبول يعيد موديول Enrollments الطالب إلى Active ويفتح الوصول للكورسات.
 */
final class ReactivationReviewed extends DomainEvent
{
    public function __construct(
        public readonly string $reactivationRequestId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly ReactivationStatus $decision,
        public readonly ?string $assessmentAttemptId,
        public readonly ?string $reviewerId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.reactivation_reviewed';
    }

    public function module(): string
    {
        return 'Discipline';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'reactivation_request_id' => $this->reactivationRequestId,
            'organization_id' => $this->organizationId,
            'enrollment_id' => $this->enrollmentId,
            'decision' => $this->decision->value,
            'assessment_attempt_id' => $this->assessmentAttemptId,
            'reviewer_id' => $this->reviewerId,
        ];
    }
}
