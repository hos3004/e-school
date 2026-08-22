<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * تغيّرت حالة قيد الطالب.
 *
 * الحدث الكنسي لكل انتقال في دورة حياة القيد، بما فيه الإنشاء (from = null).
 * المستمعون في الموديولات الأخرى يعتمدون على from/to فقط — لا Eloquent هنا.
 */
final class EnrollmentStatusChanged extends DomainEvent
{
    public function __construct(
        public readonly string $enrollmentId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $programId,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
        public readonly ?string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'enrollments.status_changed';
    }

    public function module(): string
    {
        return 'Enrollments';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'program_id' => $this->programId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'reason' => $this->reason,
        ];
    }
}
