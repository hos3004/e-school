<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * جُمِّد قيد الطالب — تأديبيًا آليًا أو يدويًا من الإدارة.
 *
 * التجميد يمنع الوصول للكورسات فقط؛ الحساب وسجله لا يُمسّان أبدًا.
 * موديول الوصول/التعلم يستمع لهذا الحدث لسحب صلاحية المحتوى فورًا.
 */
final class EnrollmentFrozen extends DomainEvent
{
    public function __construct(
        public readonly string $enrollmentId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $programId,
        public readonly ?string $fromStatus,
        public readonly string $freezeType,
        public readonly ?string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'enrollments.frozen';
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
            'freeze_type' => $this->freezeType,
            'reason' => $this->reason,
        ];
    }
}
