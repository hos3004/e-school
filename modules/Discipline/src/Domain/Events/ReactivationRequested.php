<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * قدَّم طالب (أو وليّه) طلب إعادة تفعيل لتسجيل مجمّد.
 *
 * يستمع له موديول Notifications لإبلاغ فريق الإدارة بوجود طلب بانتظار المراجعة.
 */
final class ReactivationRequested extends DomainEvent
{
    public function __construct(
        public readonly string $reactivationRequestId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly int $attemptNumber,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.reactivation_requested';
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
            'attempt_number' => $this->attemptNumber,
        ];
    }
}
